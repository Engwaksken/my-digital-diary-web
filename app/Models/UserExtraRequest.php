<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserExtraRequest extends Model
{
    use HasFactory;

    protected $table = 'user_extra_requests';

    protected $fillable = [
        'user_id',
        'request_type',
        'description',
        'status',
        'amount',
        'currency',
        'iotec_transaction_id',
        'quota_amount',
        'quota_used',
        'applied_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quota_amount' => 'integer',
        'quota_used' => 'integer',
        'applied_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function iotecTransaction(): BelongsTo
    {
        return $this->belongsTo(IoTecSubscriptionTransaction::class, 'iotec_transaction_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function remainingQuota(): int
    {
        return max(0, $this->quota_amount - $this->quota_used);
    }
}
