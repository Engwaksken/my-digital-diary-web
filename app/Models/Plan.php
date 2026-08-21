<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'personal_goal_id', 'title', 'period', 'description', 'target_date', 'reminder_at',
        'status', 'plan_year', 'plan_month', 'progress_percent', 'is_archived',
    ];

    protected $casts = [
        'target_date' => 'date',
        'reminder_at' => 'datetime',
        'plan_year' => 'integer',
        'plan_month' => 'integer',
        'progress_percent' => 'integer',
    ];

    public function personalGoal() { return $this->belongsTo(PersonalGoal::class, 'personal_goal_id'); }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
