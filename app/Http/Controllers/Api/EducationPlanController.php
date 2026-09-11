<?php

namespace App\Http\Controllers\Api;

use App\Models\EducationPlan;

class EducationPlanController extends ApiCrudController
{
    protected string $model = EducationPlan::class;

    protected array $rules = [
        'title' => 'required|string|max:255',
        'level' => 'required|in:course,certificate,associate,bachelor,master,phd,bootcamp,other',
        'status' => 'required|in:planned,in_progress,completed,on_hold',
        'institution' => 'nullable|string|max:255',
        'start_date' => 'nullable|date',
        'target_completion_date' => 'nullable|date',
        'cost' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string',
    ];

    protected function resolveDateColumn(string $table): ?string
    {
        return 'target_completion_date';
    }
}
