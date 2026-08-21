<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalReflection extends Model
{
    protected $fillable = [
        'user_id','personal_goal_id','goal_milestone_id','reflection_type',
        'what_worked','challenges','lessons_learned','repeat_next_time',
        'change_next_time','confidence_after','reflected_on',
    ];

    protected $casts = [
        'confidence_after' => 'integer',
        'reflected_on' => 'date',
    ];

    public function user(){ return $this->belongsTo(User::class); }
    public function goal(){ return $this->belongsTo(PersonalGoal::class, 'personal_goal_id'); }
    public function milestone(){ return $this->belongsTo(GoalMilestone::class, 'goal_milestone_id'); }
}
