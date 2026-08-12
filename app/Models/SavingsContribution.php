<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsContribution extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'savings_goal_id', 'amount', 'contributed_at', 'notes', 'is_archived',];

    protected $casts = [
        'contributed_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function goal()
    {
        return $this->belongsTo(SavingsGoal::class, 'savings_goal_id');
    }
}
