<?php

namespace App\Http\Controllers;

use App\Models\SavingsGoal;
use Illuminate\Http\Request;

class SavingsGoalController extends CrudController
{
    protected string $model = SavingsGoal::class;
    protected string $routeName = 'savings-goals';
    protected string $title = 'Savings Goal';
    protected string $icon = 'fa-solid fa-piggy-bank';
    protected string $accent = 'green';

    protected array $fields = [
        ['name' => 'name', 'label' => 'Goal Name', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Emergency Fund, New Laptop'],
        ['name' => 'target_amount', 'label' => 'Target Amount', 'type' => 'number', 'required' => true, 'placeholder' => '0.00', 'money' => true],
        ['name' => 'target_date', 'label' => 'Target Date', 'type' => 'date'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
            'in_progress' => 'In Progress', 'completed' => 'Completed', 'paused' => 'Paused',
        ]],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'name' => 'required|string|max:255',
        'target_amount' => 'required|numeric|min:0',
        'target_date' => 'nullable|date',
        'status' => 'required|in:in_progress,completed,paused',
        'notes' => 'nullable|string',
    ];

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $goals = SavingsGoal::where('user_id', $userId)->withSum('contributions', 'amount')->get();

        $totalSaved = $goals->sum('contributions_sum_amount');
        $totalTarget = $goals->sum('target_amount');
        $inProgress = $goals->where('status', 'in_progress')->count();
        $overallProgress = $totalTarget > 0 ? round(($totalSaved / $totalTarget) * 100, 1) : 0;

        return [
            ['label' => 'Total saved', 'value' => format_money($totalSaved), 'icon' => 'fa-solid fa-piggy-bank', 'color' => 'green'],
            ['label' => 'Goals in progress', 'value' => (string) $inProgress, 'icon' => 'fa-solid fa-bullseye', 'color' => 'blue'],
            ['label' => 'Overall progress', 'value' => $overallProgress . '% of ' . format_money($totalTarget), 'icon' => 'fa-solid fa-chart-line', 'color' => 'emerald'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $goals = SavingsGoal::where('user_id', $userId)->withSum('contributions', 'amount')->limit(8)->get();

        if ($goals->isEmpty()) {
            return null;
        }

        return [
            'type' => 'bar',
            'title' => 'Saved vs Target per Goal',
            'labels' => $goals->pluck('name')->all(),
            'datasets' => [
                ['label' => 'Saved', 'data' => $goals->map(fn ($g) => (float) ($g->contributions_sum_amount ?? 0))->all()],
                ['label' => 'Target', 'data' => $goals->map(fn ($g) => (float) $g->target_amount)->all()],
            ],
        ];
    }
}
