<?php

namespace App\Http\Controllers;

use App\Models\SavingsContribution;
use App\Models\SavingsGoal;
use Illuminate\Http\Request;

class SavingsContributionController extends CrudController
{
    protected string $model = SavingsContribution::class;
    protected string $routeName = 'savings-contributions';
    protected string $title = 'Contribution';
    protected string $icon = 'fa-solid fa-coins';
    protected string $accent = 'lime';

    protected array $fields = [
        ['name' => 'savings_goal_id', 'label' => 'Savings Goal', 'type' => 'select', 'required' => true],
        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'placeholder' => '0.00', 'money' => true],
        ['name' => 'contributed_at', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'savings_goal_id' => 'required|exists:savings_goals,id',
        'amount' => 'required|numeric|min:0',
        'contributed_at' => 'required|date',
        'notes' => 'nullable|string',
    ];

    /** Populate the "savings goal" select options with the user's own goals. */
    private function withGoalOptions(Request $request): array
    {
        $fields = $this->fields;
        $goals = SavingsGoal::where('user_id', $request->user()->id)->pluck('name', 'id')->toArray();
        $fields[0]['options'] = $goals;

        return $fields;
    }

    public function index(Request $request)
    {
        $query = SavingsContribution::where('user_id', $request->user()->id)->with('goal');

        return $this->renderIndex($request, $query, ['fields' => $this->withGoalOptions($request)]);
    }

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = SavingsContribution::where('user_id', $userId);

        $thisMonth = (clone $base)->whereBetween('contributed_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $allTime = (clone $base)->sum('amount');

        return [
            ['label' => 'This month', 'value' => format_money($thisMonth), 'icon' => 'fa-solid fa-coins', 'color' => 'lime'],
            ['label' => 'All-time total', 'value' => format_money($allTime), 'icon' => 'fa-solid fa-sack-dollar', 'color' => 'green'],
            ['label' => 'Contributions logged', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $months = collect(range(5, 0))->map(fn ($m) => now()->subMonthsNoOverflow($m)->startOfMonth());

        $totals = $months->map(function ($month) use ($userId) {
            return (float) SavingsContribution::where('user_id', $userId)
                ->whereBetween('contributed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        });

        if ($totals->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'line',
            'title' => 'Contributions (last 6 months)',
            'labels' => $months->map(fn ($m) => $m->format('M Y'))->all(),
            'datasets' => [['label' => 'Contributed', 'data' => $totals->all()]],
        ];
    }

    public function create(Request $request)
    {
        return view('crud.form', [
            'item' => new SavingsContribution,
            'fields' => $this->withGoalOptions($request),
            'title' => $this->title,
            'routeName' => $this->routeName,
            'icon' => $this->icon,
            'accent' => $this->accent,
        ]);
    }

    public function edit(Request $request, int $id)
    {
        $item = SavingsContribution::where('user_id', $request->user()->id)->findOrFail($id);

        return view('crud.form', [
            'item' => $item,
            'fields' => $this->withGoalOptions($request),
            'title' => $this->title,
            'routeName' => $this->routeName,
            'icon' => $this->icon,
            'accent' => $this->accent,
        ]);
    }
}
