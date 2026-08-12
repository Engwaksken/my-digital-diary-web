<?php

namespace App\Http\Controllers;

use App\Models\Income;

class IncomeController extends CrudController
{
    protected string $model = Income::class;
    protected string $routeName = 'incomes';
    protected string $title = 'Income';
    protected string $icon = 'fa-solid fa-money-bill-trend-up';
    protected string $accent = 'emerald';
    protected string $dateField = 'received_at';

    protected array $fields = [
        ['name' => 'source', 'label' => 'Source (e.g. Salary, Freelance)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Acme Corp Salary'],
        ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'placeholder' => 'e.g. Employment, Business, Gift'],
        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'placeholder' => '0.00', 'money' => true],
        ['name' => 'frequency', 'label' => 'Frequency', 'type' => 'select', 'required' => true, 'options' => [
            'one_time' => 'One-time', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'annually' => 'Annually',
        ]],
        ['name' => 'received_at', 'label' => 'Date Received', 'type' => 'date', 'required' => true],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'source' => 'required|string|max:255',
        'category' => 'nullable|string|max:255',
        'amount' => 'required|numeric|min:0',
        'frequency' => 'required|in:one_time,daily,weekly,monthly,annually',
        'received_at' => 'required|date',
        'notes' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = Income::where('user_id', $userId);
        $thisMonth = (clone $base)->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $thisYear = (clone $base)->whereBetween('received_at', [now()->startOfYear(), now()->endOfYear()])->sum('amount');

        return [
            ['label' => 'This month', 'value' => format_money($thisMonth), 'icon' => 'fa-solid fa-money-bill-wave', 'color' => 'emerald'],
            ['label' => 'This year', 'value' => format_money($thisYear), 'icon' => 'fa-solid fa-calendar-check', 'color' => 'teal'],
            ['label' => 'Entries', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $months = collect(range(5, 0))->map(fn ($m) => now()->subMonthsNoOverflow($m)->startOfMonth());

        $totals = $months->map(function ($month) use ($userId) {
            return (float) Income::where('user_id', $userId)
                ->whereBetween('received_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        });

        if ($totals->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'bar',
            'title' => 'Income (last 6 months)',
            'labels' => $months->map(fn ($m) => $m->format('M Y'))->all(),
            'datasets' => [['label' => 'Income', 'data' => $totals->all()]],
        ];
    }
}
