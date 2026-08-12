<?php

namespace App\Http\Controllers\Api;

use App\Models\Plan;

class PlanController extends ApiCrudController
{
    protected string $model = Plan::class;

    protected array $rules = [
        'title' => 'required|string|max:255',
        'period' => 'required|in:daily,weekly,monthly,annually',
        'status' => 'required|in:pending,in_progress,completed',
        'target_date' => 'nullable|date',
        'description' => 'nullable|string',
    ];
}
