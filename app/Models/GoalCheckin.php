<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalCheckin extends Model
{
    protected $fillable = ['user_id','personal_goal_id','week_start','confidence','status','planned_action','note'];
    protected $casts = ['week_start'=>'date','confidence'=>'integer'];
    public function goal(){ return $this->belongsTo(PersonalGoal::class, 'personal_goal_id'); }
    public function user(){ return $this->belongsTo(User::class); }
}
