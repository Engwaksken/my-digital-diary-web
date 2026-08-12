<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class FinancialPlannerProfile extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','current_age','retirement_age','current_retirement_savings','monthly_retirement_contribution','expected_annual_return','inflation_rate','desired_monthly_retirement_income','retirement_years'];
    protected $casts = [
        'current_retirement_savings'=>'decimal:2','monthly_retirement_contribution'=>'decimal:2',
        'expected_annual_return'=>'decimal:2','inflation_rate'=>'decimal:2','desired_monthly_retirement_income'=>'decimal:2'
    ];
    public function user(){ return $this->belongsTo(User::class); }
}
