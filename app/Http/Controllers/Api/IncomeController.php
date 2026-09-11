<?php

namespace App\Http\Controllers\Api;

use App\Models\Income;
use App\Services\IncomeSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function stats(Request $request): JsonResponse
    {
        $summary = app(IncomeSummaryService::class)
            ->forUser($request->user());

        return response()->json([
            ...$summary,
            'cards' => [
                ['label' => 'Total Income', 'value' => $summary['total_income']],
                ['label' => 'Monthly Income', 'value' => $summary['monthly_income']],
                ['label' => 'Balance', 'value' => $summary['balance']],
                ['label' => 'Total Income Out', 'value' => $summary['total_income_out']],
            ],
        ]);
    }
}
