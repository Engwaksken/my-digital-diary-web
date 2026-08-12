<?php

namespace App\Http\Controllers;

use App\Models\PersonalRelationship;

class PersonalRelationshipController extends CrudController
{
    protected string $model = PersonalRelationship::class;
    protected string $routeName = 'relationships';
    protected string $title = 'Relationship';
    protected string $icon = 'fa-solid fa-heart';
    protected string $accent = 'red';

    protected array $fields = [
        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
        ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => [
            'family' => 'Family', 'work' => 'Work', 'friend' => 'Friend', 'romantic' => 'Romantic', 'other' => 'Other',
        ]],
        ['name' => 'relation_label', 'label' => 'Relationship (e.g. Spouse, Manager, Sister)', 'type' => 'text', 'placeholder' => 'e.g. Spouse'],
        ['name' => 'priority', 'label' => 'Priority', 'type' => 'select', 'required' => true, 'options' => [
            'high' => 'High', 'medium' => 'Medium', 'low' => 'Low',
        ]],
        ['name' => 'last_meaningful_interaction', 'label' => 'Last Meaningful Interaction', 'type' => 'date'],
        ['name' => 'next_planned_interaction', 'label' => 'Next Planned Interaction', 'type' => 'date'],
        ['name' => 'strengthening_goal', 'label' => "How I'll Strengthen This", 'type' => 'textarea'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'name' => 'required|string|max:255',
        'category' => 'required|in:family,work,friend,romantic,other',
        'relation_label' => 'nullable|string|max:255',
        'priority' => 'required|in:high,medium,low',
        'last_meaningful_interaction' => 'nullable|date',
        'next_planned_interaction' => 'nullable|date',
        'strengthening_goal' => 'nullable|string',
        'notes' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = PersonalRelationship::where('user_id', $userId);

        return [
            ['label' => 'Overdue check-ins', 'value' => (string) (clone $base)->whereNotNull('next_planned_interaction')->where('next_planned_interaction', '<', now())->count(), 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => 'rose'],
            ['label' => 'High priority', 'value' => (string) (clone $base)->where('priority', 'high')->count(), 'icon' => 'fa-solid fa-star', 'color' => 'amber'],
            ['label' => 'Total tracked', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-heart', 'color' => 'red'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = PersonalRelationship::where('user_id', $userId)
            ->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');

        if ($counts->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Relationships by Category',
            'labels' => $counts->keys()->map(fn ($l) => ucfirst($l))->all(),
            'datasets' => [['data' => $counts->values()->all()]],
        ];
    }
}
