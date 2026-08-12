<?php

namespace App\Http\Controllers;

use App\Models\HealthCheckup;

class HealthCheckupController extends CrudController
{
    protected string $model = HealthCheckup::class;
    protected string $routeName = 'health-checkups';
    protected string $title = 'Health Checkup';
    protected string $icon = 'fa-solid fa-stethoscope';
    protected string $accent = 'pink';
    protected string $dateField = 'checkup_date';

    protected array $fields = [
        ['name' => 'checkup_type', 'label' => 'Type (e.g. Dental, General, Eye)', 'type' => 'text', 'required' => true],
        ['name' => 'checkup_date', 'label' => 'Checkup Date & Time', 'type' => 'datetime-local', 'required' => true],
        ['name' => 'doctor_name', 'label' => 'Doctor / Clinic', 'type' => 'text', 'placeholder' => 'e.g. Dr. Smith, City Dental Clinic'],
        ['name' => 'next_due_date', 'label' => 'Next Checkup Due (Date & Time)', 'type' => 'datetime-local', 'hint' => 'Leave blank if there is no follow-up scheduled yet.'],
        ['name' => 'findings', 'label' => 'Findings / Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'checkup_type' => 'required|string|max:255',
        'checkup_date' => 'required|date',
        'doctor_name' => 'nullable|string|max:255',
        'next_due_date' => 'nullable|date',
        'findings' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = HealthCheckup::where('user_id', $userId);

        return [
            ['label' => 'Upcoming', 'value' => (string) (clone $base)->whereNotNull('next_due_date')->where('next_due_date', '>=', now())->count(), 'icon' => 'fa-solid fa-calendar-days', 'color' => 'blue'],
            ['label' => 'Overdue', 'value' => (string) (clone $base)->whereNotNull('next_due_date')->where('next_due_date', '<', now())->count(), 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => 'rose'],
            ['label' => 'Total records', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-stethoscope', 'color' => 'pink'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = HealthCheckup::where('user_id', $userId)
            ->selectRaw('checkup_type, COUNT(*) as total')->groupBy('checkup_type')->orderByDesc('total')->limit(8)->pluck('total', 'checkup_type');

        if ($counts->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Checkups by Type',
            'labels' => $counts->keys()->all(),
            'datasets' => [['data' => $counts->values()->all()]],
        ];
    }
}
