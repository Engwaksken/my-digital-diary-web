<?php

namespace App\Http\Controllers;

use App\Models\BillingEventLog;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PendingSubscriptionPaymentController extends Controller
{
    /**
     * Cancel an unpaid pending subscription payment without changing the
     * primary SubscriptionController. This keeps the named route stable even
     * when that controller receives other payment-provider updates.
     */
    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless((int) $payment->user_id === (int) $request->user()->id, 403);

        if ($payment->status !== 'pending') {
            return redirect()->route('subscription.show', ['tab' => 'billing'])
                ->withErrors(['payment' => 'Only pending subscription payments can be cancelled.']);
        }

        $payment->update(['status' => 'cancelled']);

        try {
            $invoice = $payment->invoice;
            if ($invoice && $invoice->status !== 'paid') {
                $invoice->update(['status' => 'cancelled']);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            if (class_exists(BillingEventLog::class) && method_exists(BillingEventLog::class, 'record')) {
                BillingEventLog::record('pending_payment_cancelled', $request->user()->id, [
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice?->id,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('subscription.show', ['tab' => 'billing'])
            ->with('success', 'Pending subscription payment cancelled.');
    }
}
