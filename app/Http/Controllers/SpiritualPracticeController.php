<?php

namespace App\Http\Controllers;

use App\Models\SpiritualPractice;
use Illuminate\Http\Request;

class SpiritualPracticeController extends CrudController
{
    protected string $model = SpiritualPractice::class;
    protected string $routeName = 'spiritual-practices';
    protected string $title = 'Spiritual Practice';
    protected string $icon = 'fa-solid fa-hands-praying';
    protected string $accent = 'fuchsia';

    protected array $fields = [
        ['name' => 'practice_type', 'label' => 'Practice', 'type' => 'select', 'required' => true, 'options' => [
            'prayer' => 'Prayer', 'meditation' => 'Meditation', 'scripture_reading' => 'Scripture Reading',
            'worship' => 'Worship', 'fasting' => 'Fasting', 'service' => 'Service / Volunteering',
            'journaling' => 'Journaling', 'other' => 'Other',
        ]],
        ['name' => 'title', 'label' => 'Title (e.g. Morning Devotion)', 'type' => 'text', 'placeholder' => 'e.g. Morning Devotion'],
        ['name' => 'practiced_at', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['name' => 'duration_minutes', 'label' => 'Duration (minutes)', 'type' => 'number'],
        ['name' => 'next_planned_date', 'label' => 'Next Planned', 'type' => 'date'],
        ['name' => 'reflection', 'label' => 'Reflection / Journal', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'practice_type' => 'required|in:prayer,meditation,scripture_reading,worship,fasting,service,journaling,other',
        'title' => 'nullable|string|max:255',
        'practiced_at' => 'required|date',
        'duration_minutes' => 'nullable|integer|min:0',
        'next_planned_date' => 'nullable|date',
        'reflection' => 'nullable|string',
    ];

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = SpiritualPractice::where('user_id', $userId);

        $thisWeek = (clone $base)->where('practiced_at', '>=', now()->startOfWeek())->count();
        $last = (clone $base)->orderByDesc('practiced_at')->first();

        return [
            ['label' => 'This week', 'value' => (string) $thisWeek, 'icon' => 'fa-solid fa-hands-praying', 'color' => 'fuchsia'],
            ['label' => 'Total logged', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
            ['label' => 'Last practice', 'value' => $last ? $last->practiced_at->format('Y-m-d') : '—', 'icon' => 'fa-solid fa-calendar-day', 'color' => 'purple'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = SpiritualPractice::where('user_id', $userId)
            ->selectRaw('practice_type, COUNT(*) as total')->groupBy('practice_type')->pluck('total', 'practice_type');

        if ($counts->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Practices by Type',
            'labels' => $counts->keys()->map(fn ($l) => ucwords(str_replace('_', ' ', $l)))->all(),
            'datasets' => [['data' => $counts->values()->all()]],
        ];
    }
}
