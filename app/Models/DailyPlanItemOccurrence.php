<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyPlanItemOccurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_plan_item_id',
        'user_id',
        'occurrence_date',
        'is_completed',
        'completed_at',
        'is_skipped',
        'title_override',
        'description_override',
        'priority_override',
        'start_time_override',
        'end_time_override',
        'personal_goal_id_override',
    ];

    protected $casts = [
        'occurrence_date' => 'date',
        'is_completed' => 'boolean',
        'is_skipped' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(DailyPlanItem::class, 'daily_plan_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function personalGoalOverride()
    {
        return $this->belongsTo(
            PersonalGoal::class,
            'personal_goal_id_override'
        );
    }
}
