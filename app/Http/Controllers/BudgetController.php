<?php

namespace App\Http\Controllers;

use App\Models\Budget;

class BudgetController extends CrudController
{
    protected string $model = Budget::class;
    protected string $routeName = 'budgets';
    protected string $title = 'Budget';
    protected string $icon = 'fa-solid fa-wallet';
    protected string $accent = 'teal';

    protected array $fields = [
        ['name' => 'category', 'label' => 'Category (e.g. Groceries, Rent)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Groceries'],
        ['name' => 'amount', 'label' => 'Budgeted Amount', 'type' => 'number', 'required' => true, 'placeholder' => '0.00', 'money' => true],
        ['name' => 'period', 'label' => 'Period', 'type' => 'select', 'required' => true, 'options' => [
            'weekly' => 'Weekly', 'monthly' => 'Monthly', 'annually' => 'Annually',
        ]],
        ['name' => 'month_year', 'label' => 'Month/Year (e.g. 2026-08)', 'type' => 'text', 'placeholder' => '2026-08', 'hint' => 'Leave blank for an ongoing budget that applies every period.'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'category' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0',
        'period' => 'required|in:weekly,monthly,annually',
        'month_year' => 'nullable|string|max:20',
        'notes' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = Budget::where('user_id', $userId);
        $monthlyTotal = (clone $base)->where('period', 'monthly')->sum('amount');

        return [
            ['label' => 'Monthly budget total', 'value' => format_money($monthlyTotal), 'icon' => 'fa-solid fa-wallet', 'color' => 'teal'],
            ['label' => 'Categories budgeted', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-tags', 'color' => 'indigo'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $byCategory = Budget::where('user_id', $userId)
            ->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category');

        if ($byCategory->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Budget Allocation by Category',
            'labels' => $byCategory->keys()->all(),
            'datasets' => [['data' => $byCategory->values()->map(fn ($v) => (float) $v)->all()]],
        ];
    }
}
