<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionRecordingExtraGrant extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'payment_id',
        'activation_event_key',
        'activation_event_type',
        'included_minutes',
        'granted_at',
        'expires_at',
    ];

    protected $casts = [
        'included_minutes' => 'integer',
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
