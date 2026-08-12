<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class DailyPlanItem extends Model
{
    use HasFactory;
    protected $fillable=['daily_plan_id','title','description','priority','start_time','end_time','is_completed','completed_at','sort_order'];
    protected $casts=['is_completed'=>'boolean','completed_at'=>'datetime'];
    public function plan(){ return $this->belongsTo(DailyPlan::class,'daily_plan_id'); }
}
