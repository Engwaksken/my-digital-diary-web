<?php
namespace App\Http\Controllers;

use App\Models\DailyWellbeingLog;
use Illuminate\Http\Request;

class DailyWellbeingLogController extends CrudController
{
    protected string $model = DailyWellbeingLog::class;
    protected string $routeName = 'wellbeing';
    protected string $title = 'Daily Wellbeing';
    protected string $icon = 'fa-solid fa-heart-pulse';
    protected string $accent = 'teal';
    protected string $dateField = 'log_date';
    protected array $fields = [
        ['name'=>'log_date','label'=>'Date','type'=>'date','required'=>true],
        ['name'=>'water_ml','label'=>'Water Drunk (ml)','type'=>'number','required'=>true],
        ['name'=>'water_target_ml','label'=>'Daily Water Target (ml)','type'=>'number','required'=>true],
        ['name'=>'exercise_minutes','label'=>'Exercise (minutes)','type'=>'number'],
        ['name'=>'steps','label'=>'Steps (optional)','type'=>'number'],
        ['name'=>'mood','label'=>'Mood','type'=>'select','options'=>['low'=>'Low','okay'=>'Okay','good'=>'Good','great'=>'Great']],
        ['name'=>'self_care_done','label'=>'Self-care completed','type'=>'select','options'=>['1'=>'Yes','0'=>'No']],
        ['name'=>'screen_break_done','label'=>'Took a screen break','type'=>'select','options'=>['1'=>'Yes','0'=>'No']],
        ['name'=>'reflection_done','label'=>'Reflection / prayer / meditation','type'=>'select','options'=>['1'=>'Yes','0'=>'No']],
        ['name'=>'self_care_activity','label'=>'Self-care activity','type'=>'text'],
        ['name'=>'notes','label'=>'Notes','type'=>'textarea'],
    ];
    protected array $rules = [
        'log_date'=>'required|date','water_ml'=>'required|integer|min:0|max:20000','water_target_ml'=>'required|integer|min:250|max:20000','exercise_minutes'=>'nullable|integer|min:0|max:1440','steps'=>'nullable|integer|min:0|max:200000','mood'=>'nullable|in:low,okay,good,great','self_care_done'=>'nullable|boolean','screen_break_done'=>'nullable|boolean','reflection_done'=>'nullable|boolean','self_care_activity'=>'nullable|string|max:255','notes'=>'nullable|string'
    ];
    public function store(Request $request)
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;
        DailyWellbeingLog::updateOrCreate(
            ['user_id' => $request->user()->id, 'log_date' => $data['log_date']],
            $data
        );
        return redirect()->route('wellbeing.index')->with('success', 'Today\'s wellbeing updated.');
    }

    protected function stats(Request $request): array
    {
        $today = DailyWellbeingLog::where('user_id',$request->user()->id)->whereDate('log_date',now($request->user()->timezone ?: 'Africa/Kampala')->toDateString())->first();
        $water = $today?->water_target_ml ? min(100,(int)round($today->water_ml/$today->water_target_ml*100)) : 0;
        return [
            ['label'=>'Water today','value'=>$water.'%','icon'=>'fa-solid fa-droplet','color'=>'sky'],
            ['label'=>'Exercise today','value'=>(string)($today?->exercise_minutes ?? 0).' min','icon'=>'fa-solid fa-person-running','color'=>'emerald'],
            ['label'=>'Self-care','value'=>$today?->self_care_done ? 'Done' : 'Not yet','icon'=>'fa-solid fa-spa','color'=>'rose'],
        ];
    }
}
