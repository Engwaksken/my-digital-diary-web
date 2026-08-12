<?php

namespace App\Http\Controllers\Api;

use App\Models\ExerciseLog;

class ExerciseLogController extends ApiCrudController
{
    protected string $model = ExerciseLog::class;

    protected array $rules = [
        'activity' => 'required|string|max:255',
        'duration_minutes' => 'required|integer|min:1|max:1440',
        'intensity' => 'required|in:light,moderate,intense',
        'calories_burned' => 'nullable|integer|min:0',
        'performed_at' => 'required|date',
        'notes' => 'nullable|string',
    ];
}
