<?php
namespace App\Http\Controllers\Api;
use App\Models\PersonalGoal;
use Illuminate\Http\Request;
class PersonalGoalController extends ApiCrudController
{
    protected string $model = PersonalGoal::class;
    protected array $rules = ['module'=>'required|in:finance,savings,education,spiritual,health,exercise,diet,productivity,projects,personal','title'=>'required|string|max:255','description'=>'nullable|string','start_date'=>'nullable|date','target_date'=>'nullable|date|after_or_equal:start_date','target_value'=>'nullable|numeric|min:0','current_value'=>'nullable|numeric|min:0','progress_percent'=>'required|integer|min:0|max:100','status'=>'required|in:not_started,in_progress,completed,paused','priority'=>'required|in:low,medium,high','reminder_at'=>'nullable|date','notes'=>'nullable|string'];
    protected function filteredIndexQuery(Request $request) { $q=parent::filteredIndexQuery($request); if($request->filled('module')) $q->where('module',$request->query('module')); return $q; }
}
