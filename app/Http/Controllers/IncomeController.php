<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Services\IncomeSummaryService;
use Illuminate\Http\Request;

class IncomeController extends CrudController
{
    protected string $model = Income::class;
    protected string $routeName = 'incomes';
    protected string $title = 'Income';
    protected string $icon = 'fa-solid fa-money-bill-trend-up';
    protected string $accent = 'emerald';
    protected string $dateField = 'received_at';

    protected array $fields = [
        ['name' => 'source', 'label' => 'Source (e.g. Salary, Freelance)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Salary'],
        ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'placeholder' => 'e.g. Employment, Business, Gift'],
        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'placeholder' => '0.00', 'money' => true],
        ['name' => 'frequency', 'label' => 'Frequency', 'type' => 'select', 'required' => true, 'options' => [
            'one_time' => 'One-time',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'annually' => 'Annually',
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

    protected function stats(Request $request): array
    {
        $summary = app(IncomeSummaryService::class)
            ->forUser($request->user());

        return [
            [
                'label' => 'Total Income',
                'value' => format_money($summary['total_income']),
                'icon' => 'fa-solid fa-sack-dollar',
                'color' => 'emerald',
            ],
            [
                'label' => 'Monthly Income',
                'value' => format_money($summary['monthly_income']),
                'icon' => 'fa-solid fa-calendar-days',
                'color' => 'teal',
            ],
            [
                'label' => 'Balance',
                'value' => format_money($summary['balance']),
                'icon' => 'fa-solid fa-scale-balanced',
                'color' => $summary['balance'] >= 0 ? 'indigo' : 'rose',
            ],
            [
                'label' => 'Total Income Out',
                'value' => format_money($summary['total_income_out']),
                'icon' => 'fa-solid fa-arrow-up-right-from-square',
                'color' => 'amber',
            ],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $months = collect(range(5, 0))
            ->map(fn ($monthsAgo) => now()
                ->subMonthsNoOverflow($monthsAgo)
                ->startOfMonth());

        $totals = $months->map(
            fn ($month) => (float) Income::query()
                ->where('user_id', $userId)
                ->whereBetween('received_at', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ])
                ->sum('amount')
        );

        if ($totals->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'bar',
            'title' => 'Income (last 6 months)',
            'labels' => $months->map(fn ($month) => $month->format('M Y'))->all(),
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => $totals->all(),
                ],
            ],
        ];
    }
}
