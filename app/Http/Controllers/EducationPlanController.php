<?php

namespace App\Http\Controllers;

use App\Models\EducationPlan;

class EducationPlanController extends CrudController
{
    protected string $model = EducationPlan::class;
    protected string $routeName = 'education-plans';
    protected string $title = 'Education Plan';
    protected string $icon = 'fa-solid fa-graduation-cap';
    protected string $accent = 'purple';
    protected string $dateField = 'target_completion_date';

    protected array $fields = [
        ['name' => 'title', 'label' => 'Title (e.g. AWS Certification, MBA)', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. AWS Solutions Architect Certification'],
        ['name' => 'level', 'label' => 'Level', 'type' => 'select', 'required' => true, 'options' => [
            'course' => 'Course', 'certificate' => 'Certificate', 'associate' => 'Associate Degree',
            'bachelor' => "Bachelor's", 'master' => "Master's", 'phd' => 'PhD',
            'bootcamp' => 'Bootcamp', 'other' => 'Other',
        ]],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
            'planned' => 'Planned', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'on_hold' => 'On Hold',
        ]],
        ['name' => 'institution', 'label' => 'Institution / Provider', 'type' => 'text'],
        ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date'],
        ['name' => 'target_completion_date', 'label' => 'Target Completion', 'type' => 'date'],
        ['name' => 'cost', 'label' => 'Estimated Cost', 'type' => 'number', 'money' => true],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'title' => 'required|string|max:255',
        'level' => 'required|in:course,certificate,associate,bachelor,master,phd,bootcamp,other',
        'status' => 'required|in:planned,in_progress,completed,on_hold',
        'institution' => 'nullable|string|max:255',
        'start_date' => 'nullable|date',
        'target_completion_date' => 'nullable|date',
        'cost' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = EducationPlan::where('user_id', $userId);
        $totalCost = (clone $base)->sum('cost');

        return [
            ['label' => 'In progress', 'value' => (string) (clone $base)->where('status', 'in_progress')->count(), 'icon' => 'fa-solid fa-book-open', 'color' => 'purple'],
            ['label' => 'Completed', 'value' => (string) (clone $base)->where('status', 'completed')->count(), 'icon' => 'fa-solid fa-graduation-cap', 'color' => 'violet'],
            ['label' => 'Total invested', 'value' => format_money($totalCost), 'icon' => 'fa-solid fa-sack-dollar', 'color' => 'emerald'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = EducationPlan::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->isEmpty()) {
            return null;
        }

        $labels = ['planned', 'in_progress', 'completed', 'on_hold'];

        return [
            'type' => 'doughnut',
            'title' => 'Education Plans by Status',
            'labels' => array_map(fn ($l) => ucwords(str_replace('_', ' ', $l)), $labels),
            'datasets' => [['data' => array_map(fn ($l) => (int) ($counts[$l] ?? 0), $labels)]],
        ];
    }
}
