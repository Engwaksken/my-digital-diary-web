<?php

namespace App\Http\Controllers;

use App\Models\DietLog;

class DietLogController extends CrudController
{
    protected string $model = DietLog::class;
    protected string $routeName = 'diet-logs';
    protected string $title = 'Meal Log';
    protected string $icon = 'fa-solid fa-utensils';
    protected string $accent = 'orange';
    protected string $dateField = 'logged_at';

    protected array $fields = [
        ['name' => 'meal_type', 'label' => 'Meal', 'type' => 'select', 'required' => true, 'options' => [
            'breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner', 'snack' => 'Snack',
        ]],
        ['name' => 'food_items', 'label' => 'Food Items', 'type' => 'textarea', 'required' => true, 'placeholder' => 'e.g. 2 eggs, toast, orange juice'],
        ['name' => 'calories', 'label' => 'Calories', 'type' => 'number'],
        ['name' => 'logged_at', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
        'food_items' => 'required|string',
        'calories' => 'nullable|integer|min:0',
        'logged_at' => 'required|date',
        'notes' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = DietLog::where('user_id', $userId);
        $avgCalories = (clone $base)->where('logged_at', '>=', now()->subDays(7))->avg('calories');

        return [
            ['label' => 'Avg calories/day (7d)', 'value' => $avgCalories ? (string) round($avgCalories) : '—', 'icon' => 'fa-solid fa-fire', 'color' => 'orange'],
            ['label' => 'Meals logged (7d)', 'value' => (string) (clone $base)->where('logged_at', '>=', now()->subDays(7))->count(), 'icon' => 'fa-solid fa-utensils', 'color' => 'amber'],
            ['label' => 'Total meals logged', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());

        $totals = $days->map(function ($day) use ($userId) {
            return (int) DietLog::where('user_id', $userId)
                ->whereDate('logged_at', $day->toDateString())
                ->sum('calories');
        });

        if ($totals->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'line',
            'title' => 'Calories (last 14 days)',
            'labels' => $days->map(fn ($d) => $d->format('M j'))->all(),
            'datasets' => [['label' => 'Calories', 'data' => $totals->all()]],
        ];
    }
}
