<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsGoal extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'target_amount', 'target_date', 'status', 'notes', 'is_archived',];

    protected $casts = [
        'target_date' => 'date',
        'target_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contributions()
    {
        return $this->hasMany(SavingsContribution::class);
    }

    public function totalContributed(): float
    {
        return (float) $this->contributions()->sum('amount');
    }

    public function progressPercent(): float
    {
        if ((float) $this->target_amount <= 0) {
            return 0;
        }

        return min(100, round(($this->totalContributed() / (float) $this->target_amount) * 100, 1));
    }
}
