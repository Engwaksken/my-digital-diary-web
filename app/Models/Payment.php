<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'payment_gateway_id', 'subscription_plan_id', 'method', 'amount', 'currency', 'status', 'reference', 'notes', 'receipt_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Most recent gateway transaction for this payment. Automated mobile
     * money collections store the actual number used here, which is the
     * best billing contact number available for the payment record.
     */
    public function latestTransaction()
    {
        return $this->hasOne(PaymentTransactionLog::class)->latestOfMany();
    }

    public function getContactPhoneAttribute(): ?string
    {
        return $this->latestTransaction?->phone_number;
    }

    /**
     * Assigns a receipt number the first time a payment is actually
     * completed — never on creation, since a pending/failed attempt
     * shouldn't consume a receipt number. Format is YEAR-0001, resetting
     * to 0001 at the start of each new year rather than running as one
     * long sequence forever.
     */
    public function assignReceiptNumber(): void
    {
        if ($this->receipt_number) {
            return;
        }

        $year = now()->year;
        $lastNumber = static::where('receipt_number', 'like', $year . '-%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, 5)) + 1 : 1;

        $this->update(['receipt_number' => $year . '-' . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT)]);
    }
}
