<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransactionLog extends Model
{
    protected $fillable = [
        'payment_gateway_id', 'payment_id', 'user_id', 'external_reference', 'status',
        'amount', 'currency', 'phone_number', 'network', 'request_payload', 'response_payload', 'error_message',
    ];

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
