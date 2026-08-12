<?php

namespace App\Http\Controllers\Api;

use App\Models\Income;

class IncomeController extends ApiCrudController
{
    protected string $model = Income::class;

    protected array $rules = [
        'source' => 'required|string|max:255',
        'category' => 'nullable|string|max:255',
        'amount' => 'required|numeric|min:0',
        'frequency' => 'required|in:one_time,daily,weekly,monthly,annually',
        'received_at' => 'required|date',
        'notes' => 'nullable|string',
    ];
}
