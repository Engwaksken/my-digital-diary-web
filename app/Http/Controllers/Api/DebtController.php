<?php

namespace App\Http\Controllers\Api;

use App\Models\Debt;

class DebtController extends ApiCrudController
{
    protected string $model = Debt::class;

    protected array $rules = [
        'type' => 'required|in:borrowed,lent',
        'person_name' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0',
        'date' => 'required|date',
        'due_date' => 'nullable|date',
        'status' => 'required|in:outstanding,paid',
        'notes' => 'nullable|string',
    ];
}
