<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingEventLog extends Model
{
    protected $fillable = ['user_id', 'payment_id', 'invoice_id', 'event_type', 'recipient_email', 'status', 'details'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getContactPhoneAttribute(): ?string
    {
        return $this->payment?->contact_phone
            ?: $this->invoice?->contact_phone;
    }

    public static function record(string $eventType, ?int $userId, array $extra = []): void
    {
        static::create(array_merge([
            'event_type' => $eventType,
            'user_id' => $userId,
            'status' => 'success',
        ], $extra));
    }
}
