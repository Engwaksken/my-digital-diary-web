<?php

namespace App\Http\Controllers\Api;

use App\Models\Budget;

class BudgetController extends ApiCrudController
{
    protected string $model = Budget::class;

    protected array $rules = [
        'category' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0',
        'period' => 'required|in:weekly,monthly,annually',
        'month_year' => 'nullable|string|max:20',
        'notes' => 'nullable|string',
    ];
}
