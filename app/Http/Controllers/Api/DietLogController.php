<?php

namespace App\Http\Controllers\Api;

use App\Models\DietLog;

class DietLogController extends ApiCrudController
{
    protected string $model = DietLog::class;

    protected array $rules = [
        'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
        'food_items' => 'required|string',
        'calories' => 'nullable|integer|min:0',
        'logged_at' => 'required|date',
        'notes' => 'nullable|string',
    ];
}
