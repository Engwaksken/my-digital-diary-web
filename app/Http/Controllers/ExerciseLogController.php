<?php

namespace App\Http\Controllers;

use App\Models\ExerciseLog;
use Illuminate\Http\Request;
use App\Services\DailyWellbeingSyncService;

class ExerciseLogController extends CrudController
{
    protected string $model = ExerciseLog::class;
    protected string $routeName = 'exercise-logs';
    protected string $title = 'Exercise Log';
    protected string $icon = 'fa-solid fa-person-running';
    protected string $accent = 'lime';
    protected string $dateField = 'performed_at';

    protected array $fields = [
        ['name' => 'activity', 'label' => 'Activity', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Running, Weights, Yoga, Swimming'],
        ['name' => 'duration_minutes', 'label' => 'Duration (minutes)', 'type' => 'number', 'required' => true],
        ['name' => 'intensity', 'label' => 'Intensity', 'type' => 'select', 'required' => true, 'options' => [
            'light' => 'Light', 'moderate' => 'Moderate', 'intense' => 'Intense',
        ]],
        ['name' => 'calories_burned', 'label' => 'Calories Burned (optional)', 'type' => 'number'],
        ['name' => 'performed_at', 'label' => 'Date & Time', 'type' => 'datetime-local', 'required' => true],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'activity' => 'required|string|max:255',
        'duration_minutes' => 'required|integer|min:1|max:1440',
        'intensity' => 'required|in:light,moderate,intense',
        'calories_burned' => 'nullable|integer|min:0',
        'performed_at' => 'required|date',
        'notes' => 'nullable|string',
    ];

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = ExerciseLog::where('user_id', $userId);
        $thisWeekMinutes = (clone $base)->where('performed_at', '>=', now()->startOfWeek())->sum('duration_minutes');

        return [
            ['label' => 'This week', 'value' => $thisWeekMinutes . ' min', 'icon' => 'fa-solid fa-stopwatch', 'color' => 'lime'],
            ['label' => 'Sessions (7d)', 'value' => (string) (clone $base)->where('performed_at', '>=', now()->subDays(7))->count(), 'icon' => 'fa-solid fa-person-running', 'color' => 'green'],
            ['label' => 'Total logged', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-list-ol', 'color' => 'slate'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $days = collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());

        $totals = $days->map(function ($day) use ($userId) {
            return (int) ExerciseLog::where('user_id', $userId)
                ->whereDate('performed_at', $day->toDateString())
                ->sum('duration_minutes');
        });

        if ($totals->sum() <= 0) {
            return null;
        }

        return [
            'type' => 'bar',
            'title' => 'Exercise Minutes (last 14 days)',
            'labels' => $days->map(fn ($d) => $d->format('M j'))->all(),
            'datasets' => [['label' => 'Minutes', 'data' => $totals->all()]],
        ];
    }

    protected function afterSave(Request $request, $item, bool $wasCreated): void
    {
        try {
            app(DailyWellbeingSyncService::class)->sync(
                $request->user(),
                $item->performed_at ?? now()
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $item=ExerciseLog::where('user_id',$request->user()->id)->findOrFail($id);
        $performedAt=$item->performed_at;
        $item->delete();

        try {
            app(DailyWellbeingSyncService::class)->sync($request->user(),$performedAt??now());
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success','Exercise Log deleted.');
    }

}
