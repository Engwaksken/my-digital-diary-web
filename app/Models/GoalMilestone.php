<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalMilestone extends Model
{
    protected $fillable = ['user_id','personal_goal_id','title','description','target_date','status','weight','sort_order','completed_at'];
    protected $casts = ['target_date'=>'date','completed_at'=>'datetime','weight'=>'integer','sort_order'=>'integer'];

    public function goal(){ return $this->belongsTo(PersonalGoal::class, 'personal_goal_id'); }
    public function user(){ return $this->belongsTo(User::class); }
    public function reflections(){ return $this->hasMany(GoalReflection::class, 'goal_milestone_id')->latest('reflected_on')->latest('id'); }
}
