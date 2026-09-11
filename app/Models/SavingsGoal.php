<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','name','target_amount','target_date','status','notes','is_archived',
        'reminder_enabled','reminder_channel','reminder_frequency','next_reminder_at','last_reminder_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'target_amount' => 'decimal:2',
        'reminder_enabled' => 'boolean',
        'next_reminder_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function contributions() { return $this->hasMany(SavingsContribution::class); }

    public function totalContributed(): float
    {
        return (float) $this->contributions()->where('is_archived', false)->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->target_amount - $this->totalContributed());
    }

    public function progressPercent(): float
    {
        return (float) $this->target_amount > 0
            ? min(100, round(($this->totalContributed() / (float) $this->target_amount) * 100, 1))
            : 0;
    }
}
