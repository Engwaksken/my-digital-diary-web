<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class DailyPlan extends Model
{
    use HasFactory;
    protected $fillable=['user_id','plan_date','title','notes'];
    protected $casts=['plan_date'=>'date'];
    public function user(){ return $this->belongsTo(User::class); }
    public function items(){ return $this->hasMany(DailyPlanItem::class)->orderBy('sort_order')->orderBy('start_time')->orderBy('id'); }
    public function progressPercent(): int { $total=$this->items()->count(); return $total ? (int) round(($this->items()->where('is_completed',true)->count()/$total)*100) : 0; }
}
