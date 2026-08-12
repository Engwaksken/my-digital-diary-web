<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingEventLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SiteSetting;
use App\Models\SubscriptionPlan;
use App\PaymentGateways\PaymentGatewayDriverFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Mobile equivalent of SubscriptionController — same logic, duplicated
 * rather than shared (that controller's relevant helpers are private,
 * and this lives in a different area of the app). Card payments open
 * Stripe's checkout URL in an external browser (the mobile app has no
 * way to embed a secure card form itself) — payWithCardCallback() on
 * the web side handles the redirect back and was updated to not require
 * an authenticated session, since Stripe redirects a browser with none.
 */
class SubscriptionController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'subscription_status' => $user->subscription_status,
            'subscription_plan' => $user->subscriptionPlan?->name,
            'subscription_expires_at' => $user->subscription_expires_at?->toIso8601String(),
            'has_active_access' => $user->hasActiveAccess(),
        ]]);
    }

    public function plans(): JsonResponse
    {
        $settings = SiteSetting::current();
        $plans = SubscriptionPlan::where('is_enabled', true)->orderBy('category')->orderBy('sort_order')->get();

        return response()->json(['data' => $plans->map(fn ($plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'category' => $plan->category,
            'is_lifetime' => $plan->isLifetime(),
            'duration_months' => $plan->duration_months,
            'price' => $plan->computedPrice((float) $settings->monthly_price),
            'currency_code' => $settings->default_currency_code,
            'currency_symbol' => $settings->default_currency_symbol,
            'savings_label' => $plan->savingsLabel(),
            'color' => $plan->normalizedHex(),
            'badge' => $plan->badge,
            'is_recommended' => (bool) $plan->is_recommended,
            'is_best_value' => (bool) $plan->is_best_value,
            'included_members' => $plan->included_seats,
            'price_per_member' => $plan->category !== 'individual' ? $plan->pricePerSeat((float) $settings->monthly_price) : null,
            'additional_member_price' => $plan->additional_user_price ? (float) $plan->additional_user_price : null,
        ])]);
    }

    public function gateways(): JsonResponse
    {
        $gateways = PaymentGateway::where('is_enabled', true)->orderBy('type')->get();

        return response()->json(['data' => $gateways->map(fn ($g) => [
            'id' => $g->id,
            'type' => $g->type,
            'name' => $g->display_name ?: $g->name,
            'collects_automatically' => $g->collectsAutomatically(),
            'supports_mtn' => (bool) $g->supports_mtn,
            'supports_airtel' => (bool) $g->supports_airtel,
            'instructions' => $g->instructions,
            // Only meaningful for the manual flow (collects_automatically
            // false) — the account/merchant details a user needs to see
            // to actually know where to send money before submitting a
            // reference for admin verification.
            'bank_name' => $g->configValue('bank_name'),
            'account_name' => $g->configValue('account_name'),
            'account_number' => $g->configValue('account_number'),
            'routing_or_swift' => $g->configValue('routing_or_swift'),
            'provider_name' => $g->configValue('provider_name'),
            'merchant_number' => $g->configValue('merchant_number'),
        ])]);
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = $request->user()->payments()->with(['plan', 'invoice'])->orderByDesc('id')->limit(20)->get();

        return response()->json(['data' => $payments->map(fn ($p) => [
            'id' => $p->id,
            'plan' => $p->plan?->name,
            'method' => $p->method,
            'amount' => (float) $p->amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'receipt_number' => $p->receipt_number,
            'has_invoice' => (bool) $p->invoice,
            'created_at' => $p->created_at->toIso8601String(),
            'receipt_download_url' => $p->status === 'completed' ? url('/subscription/receipt/' . $p->id) : null,
            'invoice_download_url' => $p->invoice ? url('/subscription/invoice/' . $p->invoice->id) : null,
        ])]);
    }

    public function payWithCard(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id']]);
        $plan = SubscriptionPlan::where('is_enabled', true)->findOrFail($data['plan_id']);

        $gateway = PaymentGateway::where('type', 'card')->where('is_enabled', true)->first();
        if (! $gateway || ! $gateway->configValue('stripe_secret_key')) {
            return response()->json(['message' => 'Card payments are not configured yet.'], 422);
        }

        $settings = SiteSetting::current();
        $price = $plan->computedPrice((float) $settings->monthly_price);
        $currencyCode = strtolower($settings->default_currency_code);
        $isZeroDecimal = $settings->default_currency_decimals == 0;
        $unitAmount = $isZeroDecimal ? (int) round($price) : (int) round($price * 100);

        $response = Http::asForm()
            ->withToken($gateway->configValue('stripe_secret_key'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                // No mobile deep link back into the app here — the web
                // callback route (public, no session needed — see its
                // own comment) is what actually completes this, and the
                // user can just switch back to the app afterward.
                'success_url' => url('/subscription/pay/card/callback') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/subscription'),
                'customer_email' => $request->user()->email,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currencyCode,
                        'unit_amount' => $unitAmount,
                        'product_data' => ['name' => $settings->site_name . ' Subscription — ' . $plan->name],
                    ],
                ]],
            ]);

        if ($response->failed() || ! $response->json('url')) {
            Log::warning('Stripe checkout session creation failed (mobile)', ['response' => $response->json()]);

            return response()->json(['message' => 'Could not start card checkout.'], 422);
        }

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

        return response()->json(['data' => ['checkout_url' => $response->json('url')]]);
    }

    public function payWithMobileMoney(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'phone_number' => ['required', 'string', 'max:20'],
            'network' => ['required', 'in:mtn,airtel'],
        ]);

        $plan = SubscriptionPlan::where('is_enabled', true)->findOrFail($data['plan_id']);
        $gateway = PaymentGateway::whereIn('type', ['mobile_money', 'aggregator'])->where('is_default', true)->where('is_enabled', true)->first();

        if (! $gateway || ! $gateway->collectsAutomatically()) {
            return response()->json(['message' => 'Mobile money collection is not configured yet.'], 422);
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
            $driver = PaymentGatewayDriverFactory::make($gateway);
            $driver->initiateCollection($reference, $price, $settings->default_currency_code, $data['phone_number'], $data['network'], $request->user()->id, $payment->id);

            return response()->json(['message' => 'A payment prompt has been sent to ' . $data['phone_number'] . ' — approve it on your phone.']);
        } catch (\Throwable $e) {
            Log::warning('IoTec collection request failed (mobile)', ['error' => $e->getMessage()]);
            $payment->update(['status' => 'failed']);

            return response()->json(['message' => 'Could not start the mobile money request: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Mobile equivalent of the web app's submitManualPayment() — a
     * user has ALREADY sent money themselves (bank transfer, or
     * manually to a mobile money merchant number) using the account
     * details shown by gateways() above, and is now submitting their
     * own reference/transaction ID for admin verification. Creates a
     * pending Payment, same as the automated flows, just without a
     * live API confirming it — that's why it stays "pending" rather
     * than being marked completed here.
     */
    public function submitManualPayment(Request $request): JsonResponse
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

        $plan = SubscriptionPlan::where('is_enabled', true)->findOrFail($data['plan_id']);
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

        return response()->json(['message' => 'Thanks — your payment reference was submitted and is awaiting verification.']);
    }

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
                'invoice_id' => $invoice->id, 'recipient_email' => $user->email, 'status' => 'failed', 'details' => $e->getMessage(),
            ]);
        }

        return $invoice;
    }
}
