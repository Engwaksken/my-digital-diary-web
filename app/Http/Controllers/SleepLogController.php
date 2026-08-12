<?php

namespace App\Http\Controllers;

use App\Models\SleepLog;

class SleepLogController extends CrudController
{
    protected string $model = SleepLog::class;
    protected string $routeName = 'sleep-logs';
    protected string $title = 'Sleep Log';
    protected string $icon = 'fa-solid fa-bed';
    protected string $accent = 'violet';
    protected string $dateField = 'sleep_date';

    protected array $fields = [
        ['name' => 'sleep_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'bed_time', 'label' => 'Bed Time', 'type' => 'time'],
        ['name' => 'wake_time', 'label' => 'Wake Time', 'type' => 'time'],
        ['name' => 'quality', 'label' => 'Quality', 'type' => 'select', 'required' => true, 'options' => [
            'poor' => 'Poor', 'fair' => 'Fair', 'good' => 'Good', 'excellent' => 'Excellent',
        ]],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'sleep_date' => 'required|date',
        'bed_time' => 'nullable|date_format:H:i',
        'wake_time' => 'nullable|date_format:H:i',
        'quality' => 'required|in:poor,fair,good,excellent',
        'notes' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = SleepLog::where('user_id', $userId);
        $avgMinutes = (clone $base)->where('sleep_date', '>=', now()->subDays(7))->avg('duration_minutes');

        return [
            ['label' => 'Avg sleep (7d)', 'value' => $avgMinutes ? number_format($avgMinutes / 60, 1) . ' hrs' : '—', 'icon' => 'fa-solid fa-moon', 'color' => 'violet'],
            ['label' => 'Nights logged (7d)', 'value' => (string) (clone $base)->where('sleep_date', '>=', now()->subDays(7))->count(), 'icon' => 'fa-solid fa-bed', 'color' => 'indigo'],
            ['label' => 'Total nights logged', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());

        $totals = $days->map(function ($day) use ($userId) {
            $minutes = (int) SleepLog::where('user_id', $userId)
                ->whereDate('sleep_date', $day->toDateString())
                ->sum('duration_minutes');

            return round($minutes / 60, 1);
        });

        if ($totals->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'line',
            'title' => 'Sleep Hours (last 14 nights)',
            'labels' => $days->map(fn ($d) => $d->format('M j'))->all(),
            'datasets' => [['label' => 'Hours', 'data' => $totals->all()]],
        ];
    }
}
