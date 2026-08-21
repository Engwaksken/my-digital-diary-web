<?php
namespace App\Http\Controllers;

use App\Models\PersonalGoal;
use Illuminate\Http\Request;

class PersonalGoalController extends CrudController
{
    protected string $model = PersonalGoal::class;
    protected string $routeName = 'personal-goals';
    protected string $title = 'Goal';
    protected string $icon = 'fa-solid fa-bullseye';
    protected string $accent = 'violet';
    protected string $dateField = 'target_date';

    protected array $fields = [
        ['name'=>'module','label'=>'Life Area','type'=>'select','required'=>true,'options'=>[
            'finance'=>'Finance','savings'=>'Savings','education'=>'Education','spiritual'=>'Spiritual Growth','health'=>'Health & Self-care','exercise'=>'Exercise & Fitness','diet'=>'Diet & Nutrition','productivity'=>'Productivity','projects'=>'Projects','personal'=>'Personal Development'
        ]],
        ['name'=>'title','label'=>'Goal','type'=>'text','required'=>true],
        ['name'=>'description','label'=>'Why this matters','type'=>'textarea'],
        ['name'=>'start_date','label'=>'Start Date','type'=>'date'],
        ['name'=>'target_date','label'=>'Target Date','type'=>'date'],
        ['name'=>'target_value','label'=>'Target Value (optional)','type'=>'number'],
        ['name'=>'current_value','label'=>'Current Value (optional)','type'=>'number'],
        ['name'=>'progress_percent','label'=>'Progress %','type'=>'number','required'=>true],
        ['name'=>'status','label'=>'Status','type'=>'select','required'=>true,'options'=>['not_started'=>'Not started','in_progress'=>'In progress','completed'=>'Completed','paused'=>'Paused']],
        ['name'=>'priority','label'=>'Priority','type'=>'select','required'=>true,'options'=>['low'=>'Low','medium'=>'Medium','high'=>'High']],
        ['name'=>'reminder_at','label'=>'Reminder','type'=>'datetime-local'],
        ['name'=>'notes','label'=>'Notes','type'=>'textarea'],
    ];

    protected array $rules = [
        'module'=>'required|in:finance,savings,education,spiritual,health,exercise,diet,productivity,projects,personal',
        'title'=>'required|string|max:255','description'=>'nullable|string','start_date'=>'nullable|date','target_date'=>'nullable|date|after_or_equal:start_date',
        'target_value'=>'nullable|numeric|min:0','current_value'=>'nullable|numeric|min:0','progress_percent'=>'required|integer|min:0|max:100',
        'status'=>'required|in:not_started,in_progress,completed,paused','priority'=>'required|in:low,medium,high','reminder_at'=>'nullable|date','notes'=>'nullable|string'
    ];

    public function index(Request $request)
    {
        $query = PersonalGoal::where('user_id', $request->user()->id);
        if ($request->filled('module')) $query->where('module', $request->string('module')->toString());
        return $this->renderIndex($request, $query, ['selectedModule'=>$request->query('module')], fn($q)=>$q->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->orderBy('target_date'));
    }

    protected function stats(Request $request): array
    {
        $q = PersonalGoal::where('user_id',$request->user()->id)->where('is_archived',false);
        if ($request->filled('module')) $q->where('module',$request->query('module'));
        return [
            ['label'=>'Active','value'=>(string)(clone $q)->whereIn('status',['not_started','in_progress'])->count(),'icon'=>'fa-solid fa-bullseye','color'=>'violet'],
            ['label'=>'Completed','value'=>(string)(clone $q)->where('status','completed')->count(),'icon'=>'fa-solid fa-circle-check','color'=>'emerald'],
            ['label'=>'Average progress','value'=>((int)round((clone $q)->avg('progress_percent') ?? 0)).'%','icon'=>'fa-solid fa-chart-line','color'=>'sky'],
        ];
    }
}
