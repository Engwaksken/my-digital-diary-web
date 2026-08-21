<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyWellbeingLog extends Model
{
    protected $fillable = ['user_id','log_date','water_ml','water_target_ml','exercise_minutes','steps','mood','self_care_done','screen_break_done','reflection_done','self_care_activity','notes','is_archived'];
    protected $casts = ['log_date'=>'date','water_ml'=>'integer','water_target_ml'=>'integer','exercise_minutes'=>'integer','steps'=>'integer','self_care_done'=>'boolean','screen_break_done'=>'boolean','reflection_done'=>'boolean','is_archived'=>'boolean'];
}
