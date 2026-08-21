<?php
namespace App\Http\Controllers\Api;
use App\Models\DailyWellbeingLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
class DailyWellbeingLogController extends ApiCrudController
{
    protected string $model = DailyWellbeingLog::class;
    protected array $rules = ['log_date'=>'required|date','water_ml'=>'required|integer|min:0|max:20000','water_target_ml'=>'required|integer|min:250|max:20000','exercise_minutes'=>'nullable|integer|min:0|max:1440','steps'=>'nullable|integer|min:0|max:200000','mood'=>'nullable|in:low,okay,good,great','self_care_done'=>'nullable|boolean','screen_break_done'=>'nullable|boolean','reflection_done'=>'nullable|boolean','self_care_activity'=>'nullable|string|max:255','notes'=>'nullable|string'];
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;
        $item = DailyWellbeingLog::updateOrCreate(['user_id'=>$request->user()->id,'log_date'=>$data['log_date']], $data);
        return response()->json($item, $item->wasRecentlyCreated ? 201 : 200);
    }
}
