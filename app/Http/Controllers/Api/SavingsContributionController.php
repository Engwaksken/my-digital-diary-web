<?php

namespace App\Http\Controllers\Api;

use App\Models\SavingsContribution;

class SavingsContributionController extends ApiCrudController
{
    protected string $model = SavingsContribution::class;

    protected array $rules = [
        'savings_goal_id' => 'required|exists:savings_goals,id',
        'amount' => 'required|numeric|min:0',
        'contributed_at' => 'required|date',
        'notes' => 'nullable|string',
    ];
}
