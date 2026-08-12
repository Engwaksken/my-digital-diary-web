<?php

namespace App\Http\Controllers;

use App\Models\BillingEventLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SiteSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Real payment gateways are admin-configured (see AdminPaymentGatewayController
 * / /admin/payment-gateways) rather than hardcoded here:
 *
 *   - card: a live Stripe Checkout redirect using the admin's own API keys
 *     (called directly via Http, no Stripe SDK/composer package needed).
 *   - bank / mobile_money: no live API — there's no single API that covers
 *     arbitrary banks or mobile money providers worldwide. Users submit a
 *     transaction reference after paying outside the app; an admin
 *     approves or rejects it at /admin/payments.
 *
 * Pricing tiers (Monthly/3mo/6mo/Annual/Lifetime, admin-configurable
 * discounts) live in SubscriptionPlan — see AdminSubscriptionPlanController.
 * Every payment path below now requires a plan_id and uses that plan's
 * computed price + duration, rather than always charging the flat monthly
 * rate.
 *
 * If NO gateways are configured at all, `subscribe()` below still works as
 * a demo/no-payment-provider fallback, exactly as before.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $settings = SiteSetting::current();

        $gateways = PaymentGateway::where('is_enabled', true)->orderBy('type')->get();
        $plans = SubscriptionPlan::where('is_enabled', true)->orderBy('sort_order')->get();

        // ---- Invoices & Receipts tab: search, period filter, pagination ----
        $search = $request->query('billing_q');
        $period = $request->query('billing_period');
        $from = $request->query('billing_from');
        $to = $request->query('billing_to');

        $paymentsQuery = $user->payments()->with(['gateway', 'plan', 'invoice'])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('plan', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            }));

        match ($period) {
            'daily' => $paymentsQuery->whereDate('created_at', now()->toDateString()),
            'weekly' => $paymentsQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'monthly' => $paymentsQuery->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'range' => ($from && $to)
                ? $paymentsQuery->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                : null,
            default => null,
        };

        $payments = $paymentsQuery->orderByDesc('id')->paginate(10, ['*'], 'billing_page')->withQueryString();

        $billingStats = [
            'total_invoices' => Invoice::where('user_id', $user->id)->count(),
            'completed' => $user->payments()->where('status', 'completed')->count(),
            'pending' => $user->payments()->where('status', 'pending')->count(),
            'failed' => $user->payments()->whereIn('status', ['failed', 'rejected'])->count(),
        ];

        return view('subscription.show', [
            'user' => $user,
            'settings' => $settings,
            'gateways' => $gateways,
            'plans' => $plans,
            'payments' => $payments,
            'billingStats' => $billingStats,
            'billingSearch' => $search,
            'billingPeriod' => $period,
            'billingFrom' => $from,
            'billingTo' => $to,
        ]);
    }

    /**
     * Delete multiple invoices from the signed-in user's own billing history.
     * Paid/cancelled invoices are retained for accounting/audit integrity;
     * only records allowed by Invoice::isDeletable() are removed.
     */
    public function bulkDestroyInvoices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer'],
        ]);

        $invoices = Invoice::where('user_id', $request->user()->id)
            ->whereIn('id', $data['invoice_ids'])
            ->get();

        $deleted = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            if ($invoice->isDeletable()) {
                $invoice->delete();
                $deleted++;
            } else {
                $skipped++;
            }
        }

        $message = $deleted === 1 ? '1 invoice deleted.' : "{$deleted} invoices deleted.";
        if ($skipped > 0) {
            $message .= " {$skipped} paid/cancelled invoice(s) kept for billing records.";
        }

        return back()->with($deleted > 0 ? 'success' : 'warning', $message);
    }

    /**
     * Delete selected completed payment/receipt records belonging to the
     * signed-in user. A receipt in this application is the completed Payment
     * row itself, so ownership is enforced before deletion.
     */
    public function bulkDestroyReceipts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'receipt_ids' => ['required', 'array', 'min:1'],
            'receipt_ids.*' => ['integer'],
        ]);

        $deleted = Payment::where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->whereIn('id', $data['receipt_ids'])
            ->delete();

        return back()->with('success', $deleted === 1
            ? '1 receipt deleted.'
            : "{$deleted} receipts deleted.");
    }

    /**
     * Given a chosen plan, works out the correct subscription_expires_at —
     * extending from the CURRENT expiry if it's still in the future
     * (so renewing before running out doesn't lose already-paid time),
     * otherwise from now. Null for the lifetime plan (never expires).
     */
    private function expiryFor(SubscriptionPlan $plan, $user): ?Carbon
    {
        if ($plan->isLifetime()) {
            return null;
        }

        $base = ($user->subscription_expires_at && $user->subscription_expires_at->isFuture())
            ? $user->subscription_expires_at
            : now();

        return $base->copy()->addMonths($plan->duration_months);
    }

    /**
     * Raised the moment a user commits to a plan by submitting any
     * payment method — represents what's owed for this billing period,
     * separate from the receipt that follows once payment actually
     * completes. Emailed immediately as a PDF attachment; every step is
     * recorded to billing_event_logs for admin visibility.
     */
    private function createAndSendInvoice(Payment $payment, SubscriptionPlan $plan, $user): Invoice
    {
        $periodStart = ($user->subscription_expires_at && $user->subscription_expires_at->isFuture())
            ? $user->subscription_expires_at->copy()
            : now();
        $periodEnd = $plan->isLifetime() ? null : $periodStart->copy()->addMonths($plan->duration_months);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'unpaid',
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd?->toDateString(),
            'due_date' => now()->toDateString(),
        ]);

        BillingEventLog::record('invoice_generated', $user->id, ['invoice_id' => $invoice->id, 'payment_id' => $payment->id]);

        try {
            Mail::to($user->email)->send(new \App\Mail\InvoiceMail($invoice));
            BillingEventLog::record('invoice_emailed', $user->id, ['invoice_id' => $invoice->id, 'recipient_email' => $user->email]);
        } catch (\Throwable $e) {
            BillingEventLog::record('invoice_emailed', $user->id, [
                'invoice_id' => $invoice->id,
                'recipient_email' => $user->email,
                'status' => 'failed',
                'details' => $e->getMessage(),
            ]);
        }

        return $invoice;
    }

    private function findEnabledPlan(int $planId): SubscriptionPlan
    {
        return SubscriptionPlan::where('is_enabled', true)->findOrFail($planId);
    }

    /**
     * Demo/no-gateway-configured fallback — flips the account to active
     * immediately with no charge. Only reachable from the UI when no
     * payment gateways are configured at all (see subscription/show.blade.php).
     */
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['nullable', 'exists:subscription_plans,id']]);
        $user = $request->user();
        $plan = ! empty($data['plan_id']) ? $this->findEnabledPlan($data['plan_id']) : null;

        $user->update([
            'subscription_status' => 'active',
            'subscribed_at' => now(),
            'subscription_plan_id' => $plan?->id,
            'subscription_expires_at' => $plan ? $this->expiryFor($plan, $user) : null,
        ]);

        if ($plan) {
            $this->ensureOrganizationForPlan($user, $plan);
        }

        return redirect()->route('dashboard')->with('success', 'Subscription activated — welcome back!');
    }

    /**
     * A Family/Team or Organization plan needs an actual Organization
     * record to manage seats against — nothing else in the app creates
     * one, so every path that activates a subscription (this one, the
     * card callback below, the webhook controller, and admin manual
     * approval) needs to call this. An individual plan does nothing
     * here. If the buyer already owns an organization (upgrading or
     * renewing their tier), that same organization's plan is updated
     * rather than creating a second one.
     */
    private function ensureOrganizationForPlan(\App\Models\User $user, SubscriptionPlan $plan): void
    {
        if ($plan->isIndividual()) {
            return;
        }

        $organization = \App\Models\Organization::where('owner_user_id', $user->id)->first();

        if ($organization) {
            $organization->update(['subscription_plan_id' => $plan->id]);
        } else {
            \App\Models\Organization::create([
                'name' => $user->name . "'s Organization",
                'owner_user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
            ]);
        }
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->user()->update([
            'subscription_status' => 'canceled',
        ]);

        return redirect()->route('subscription.show')->with('status', 'Subscription canceled.');
    }

    /**
     * Creates a Stripe Checkout Session using the admin-configured card
     * gateway's own API keys, and redirects the user to Stripe's hosted
     * checkout page. Nothing in our own database changes yet — that only
     * happens once payCardCallback() confirms Stripe says it was paid.
     */
    public function payWithCard(Request $request): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id']]);
        $plan = $this->findEnabledPlan($data['plan_id']);

        $gateway = PaymentGateway::where('type', 'card')->where('is_enabled', true)->first();

        if (! $gateway || ! $gateway->configValue('stripe_secret_key')) {
            return back()->withErrors(['payment' => 'Card payments are not configured yet.']);
        }

        $settings = SiteSetting::current();
        $price = $plan->computedPrice((float) $settings->monthly_price);
        $currencyCode = strtolower($settings->default_currency_code);

        // Stripe expects amounts in the currency's SMALLEST unit — cents
        // for USD, but for a handful of currencies Stripe treats as
        // "zero-decimal" (UGX among them), the amount is sent as-is with
        // NO ×100. Multiplying UGX by 100 here would have charged 100x
        // the intended amount. default_currency_decimals is the signal
        // used elsewhere in the app for display formatting, so this
        // stays consistent with however the admin has that configured —
        // if they ever set a currency with decimals=0 that Stripe does
        // NOT treat as zero-decimal (or vice versa), this would need
        // adjusting to match Stripe's own fixed list instead.
        $isZeroDecimal = $settings->default_currency_decimals == 0;
        $unitAmount = $isZeroDecimal ? (int) round($price) : (int) round($price * 100);

        $response = Http::asForm()
            ->withToken($gateway->configValue('stripe_secret_key'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => route('subscription.pay.card.callback') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.show'),
                'customer_email' => $request->user()->email,
                'line_items' => [
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => $currencyCode,
                            'unit_amount' => $unitAmount,
                            'product_data' => [
                                'name' => $settings->site_name . ' Subscription — ' . $plan->name,
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed() || ! $response->json('url')) {
            Log::warning('Stripe checkout session creation failed', ['response' => $response->json()]);

            return back()->withErrors(['payment' => 'Could not start card checkout. Please try again or use another payment method.']);
        }

        // Record the attempt as pending now, so it shows up even if the
        // user abandons checkout — payWithCardCallback() flips it to
        // completed once Stripe confirms payment.
        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'payment_gateway_id' => $gateway->id,
            'subscription_plan_id' => $plan->id,
            'method' => 'card',
            'amount' => $price,
            'currency' => $settings->default_currency_code,
            'status' => 'pending',
            'reference' => $response->json('id'),
        ]);

        $this->createAndSendInvoice($payment, $plan, $request->user());

        return redirect()->away($response->json('url'));
    }

    /**
     * IoTec (or any other 'aggregator'-type gateway marked as default)
     * mobile money collection — unlike the card flow, this doesn't
     * redirect anywhere; IoTec pushes a payment prompt straight to the
     * phone number entered, and the subscription activates once
     * PaymentGatewayWebhookController receives their confirmation (or the status
     * check below catches it if the webhook hasn't landed yet).
     */
    public function payWithMobileMoney(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'phone_number' => ['required', 'string', 'max:20'],
            'network' => ['required', 'in:mtn,airtel'],
        ]);

        $plan = $this->findEnabledPlan($data['plan_id']);

        $gateway = PaymentGateway::whereIn('type', ['mobile_money', 'aggregator'])->where('is_default', true)->where('is_enabled', true)->first();

        if (! $gateway || ! $gateway->collectsAutomatically()) {
            return back()->withErrors(['payment' => 'Mobile money collection is not configured yet.']);
        }

        $settings = SiteSetting::current();
        $price = $plan->computedPrice((float) $settings->monthly_price);
        $reference = 'sub_' . $request->user()->id . '_' . now()->format('YmdHis') . '_' . random_int(1000, 9999);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'payment_gateway_id' => $gateway->id,
            'subscription_plan_id' => $plan->id,
            'method' => 'mobile_money',
            'amount' => $price,
            'currency' => $settings->default_currency_code,
            'status' => 'pending',
            'reference' => $reference,
        ]);

        $this->createAndSendInvoice($payment, $plan, $request->user());

        try {
            $driver = \App\PaymentGateways\PaymentGatewayDriverFactory::make($gateway);
            $log = $driver->initiateCollection(
                $reference,
                $price,
                $settings->default_currency_code,
                $data['phone_number'],
                $data['network'],
                $request->user()->id,
                $payment->id
            );

            return redirect()->route('subscription.show')->with(
                'success',
                'A payment prompt has been sent to ' . $data['phone_number'] . ' — approve it on your phone to activate your subscription.'
            );
        } catch (\Throwable $e) {
            Log::warning('IoTec collection request failed', ['error' => $e->getMessage()]);
            $payment->update(['status' => 'failed']);

            return back()->withErrors(['payment' => 'Could not start the mobile money request: ' . $e->getMessage()]);
        }
    }

    /**
     * Stripe redirects here after checkout (success_url above). Verifies
     * the session directly with Stripe's API before trusting it — never
     * activates a subscription based solely on the redirect happening.
     */
    public function payWithCardCallback(Request $request): RedirectResponse
    {
        $sessionId = $request->query('session_id');
        // Looked up by the Stripe session ID alone, not tied to the
        // current request's session — Stripe redirects the BROWSER back
        // here, which has no session at all if checkout was started from
        // the mobile app (a Bearer-token API request, opened in an
        // external browser via a plain link). The session id itself is
        // unique and unguessable, so this is still safe.
        $payment = $sessionId ? Payment::where('reference', $sessionId)->first() : null;

        if (! $sessionId || ! $payment) {
            return redirect()->route('subscription.show')->withErrors(['payment' => 'Could not find that checkout session.']);
        }

        $gateway = $payment->gateway;

        $response = Http::withToken($gateway?->configValue('stripe_secret_key'))
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}");

        if ($response->failed()) {
            return redirect()->route('subscription.show')->withErrors(['payment' => 'Could not verify payment with Stripe.']);
        }

        if ($response->json('payment_status') === 'paid') {
            $payment->update(['status' => 'completed']);
            $payment->assignReceiptNumber();
            $payment->invoice?->update(['status' => 'paid']);

            $user = $payment->user;
            $plan = $payment->plan;

            $user->update([
                'subscription_status' => 'active',
                'subscribed_at' => $user->subscribed_at ?? now(),
                'subscription_plan_id' => $plan?->id,
                'subscription_expires_at' => $plan ? $this->expiryFor($plan, $user) : null,
                // Cleared so the NEXT expiry cycle's reminders start
                // fresh against the new expiry date, rather than
                // carrying over a milestone number from before this
                // renewal — this is also what stops any reminder in
                // flight for the old expiry date from being mistaken
                // for still relevant.
                'last_expiry_reminder_days' => null,
            ]);

            if ($plan) {
                $this->ensureOrganizationForPlan($user, $plan);
            }

            BillingEventLog::record('payment_status_changed', $user->id, ['payment_id' => $payment->id, 'details' => 'completed via card']);

            $user->notify(new \App\Notifications\PaymentSuccessfulNotification($payment));

            try {
                Mail::to($user->email)->send(new \App\Mail\PaymentReceiptMail($payment));
                BillingEventLog::record('receipt_emailed', $user->id, ['payment_id' => $payment->id, 'recipient_email' => $user->email]);
            } catch (\Throwable $e) {
                BillingEventLog::record('receipt_emailed', $user->id, [
                    'payment_id' => $payment->id, 'recipient_email' => $user->email, 'status' => 'failed', 'details' => $e->getMessage(),
                ]);
            }

            return redirect()->route('dashboard')->with('success', 'Payment received — subscription activated!');
        }

        $payment->update(['status' => 'failed']);
        BillingEventLog::record('payment_status_changed', $payment->user_id, ['payment_id' => $payment->id, 'status' => 'failed', 'details' => 'card payment not paid']);

        return redirect()->route('subscription.show')->withErrors(['payment' => 'Payment was not completed.']);
    }

    /**
     * Bank / mobile money: the user has already paid OUTSIDE the app
     * (following the gateway's displayed instructions) and is submitting
     * proof — a transaction reference — for an admin to verify at
     * /admin/payments. This does NOT activate the subscription by itself —
     * AdminPaymentsController::approve() is what actually sets
     * subscription_status/expires_at, using this payment's own plan.
     */
    public function submitManualPayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_gateway_id' => ['required', 'exists:payment_gateways,id'],
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'reference' => ['required', 'string', 'max:255'],
        ]);

        $gateway = PaymentGateway::where('id', $data['payment_gateway_id'])
            ->where('is_enabled', true)
            ->whereIn('type', ['bank', 'mobile_money'])
            ->firstOrFail();

        $plan = $this->findEnabledPlan($data['plan_id']);
        $settings = SiteSetting::current();
        $price = $plan->computedPrice((float) $settings->monthly_price);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'payment_gateway_id' => $gateway->id,
            'subscription_plan_id' => $plan->id,
            'method' => $gateway->type,
            'amount' => $price,
            'currency' => $settings->default_currency_code,
            'status' => 'pending',
            'reference' => $data['reference'],
        ]);

        $this->createAndSendInvoice($payment, $plan, $request->user());

        return back()->with('success', 'Thanks — your payment reference was submitted and is awaiting verification.');
    }

    public function downloadReceipt(Request $request, Payment $payment)
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subscription.receipt-pdf', ['payment' => $payment])->setPaper('a4');

        return $pdf->download('receipt-' . $payment->id . '.pdf');
    }

    public function downloadInvoice(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('subscription.invoice-pdf', ['invoice' => $invoice])->setPaper('a4');

        return $pdf->download($invoice->invoice_number . '.pdf');
    }
}
