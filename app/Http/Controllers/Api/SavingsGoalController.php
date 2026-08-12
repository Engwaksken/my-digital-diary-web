<?php

namespace App\Http\Controllers\Api;

use App\Models\SavingsGoal;

class SavingsGoalController extends ApiCrudController
{
    protected string $model = SavingsGoal::class;

    protected array $rules = [
        'name' => 'required|string|max:255',
        'target_amount' => 'required|numeric|min:0',
        'target_date' => 'nullable|date',
        'status' => 'required|in:in_progress,completed,paused',
        'notes' => 'nullable|string',
    ];
}
