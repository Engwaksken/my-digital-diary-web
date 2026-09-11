<?php
namespace App\Http\Controllers\Api;
use App\Models\SavingsGoal;
class SavingsGoalController extends ApiCrudController
{
    protected string $model = SavingsGoal::class;
    protected array $rules = [
        'name'=>'required|string|max:255','target_amount'=>'required|numeric|min:0.01',
        'target_date'=>'nullable|date','status'=>'required|in:in_progress,completed,paused',
        'notes'=>'nullable|string','reminder_enabled'=>'nullable|boolean',
        'reminder_channel'=>'nullable|in:email,sms,both',
        'reminder_frequency'=>'nullable|in:daily,weekly,fortnightly,monthly',
        'next_reminder_at'=>'nullable|date',
    ];
}
