<?php

namespace App\Http\Controllers;

use App\Models\NetworkContact;

class NetworkContactController extends CrudController
{
    protected string $model = NetworkContact::class;
    protected string $routeName = 'network-contacts';
    protected string $title = 'Contact';
    protected string $icon = 'fa-solid fa-people-arrows';
    protected string $accent = 'sky';

    protected array $fields = [
        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
        ['name' => 'relationship_type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => [
            'mentor' => 'Mentor', 'mentee' => 'Mentee', 'peer' => 'Peer',
            'industry_contact' => 'Industry Contact', 'recruiter' => 'Recruiter', 'client' => 'Client', 'other' => 'Other',
        ]],
        ['name' => 'company', 'label' => 'Company / Organization', 'type' => 'text', 'placeholder' => 'e.g. Acme Corp'],
        ['name' => 'met_through', 'label' => 'How You Met', 'type' => 'text', 'placeholder' => 'e.g. Conference, LinkedIn, mutual friend'],
        ['name' => 'last_contact_date', 'label' => 'Last Contact', 'type' => 'date'],
        ['name' => 'next_follow_up_date', 'label' => 'Next Follow-up', 'type' => 'date'],
        ['name' => 'goal', 'label' => 'Relationship Goal', 'type' => 'textarea'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ];

    protected array $rules = [
        'name' => 'required|string|max:255',
        'relationship_type' => 'required|in:mentor,mentee,peer,industry_contact,recruiter,client,other',
        'company' => 'nullable|string|max:255',
        'met_through' => 'nullable|string|max:255',
        'last_contact_date' => 'nullable|date',
        'next_follow_up_date' => 'nullable|date',
        'goal' => 'nullable|string',
        'notes' => 'nullable|string',
    ];

    protected function stats(\Illuminate\Http\Request $request): array
    {
        $userId = $request->user()->id;
        $base = NetworkContact::where('user_id', $userId);

        return [
            ['label' => 'Follow-ups due (7d)', 'value' => (string) (clone $base)->whereNotNull('next_follow_up_date')->where('next_follow_up_date', '<=', now()->addDays(7))->count(), 'icon' => 'fa-solid fa-user-clock', 'color' => 'sky'],
            ['label' => 'Overdue follow-ups', 'value' => (string) (clone $base)->whereNotNull('next_follow_up_date')->where('next_follow_up_date', '<', now())->count(), 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => 'rose'],
            ['label' => 'Total contacts', 'value' => (string) $base->count(), 'icon' => 'fa-solid fa-people-group', 'color' => 'blue'],
        ];
    }

    protected function chart(\Illuminate\Http\Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = NetworkContact::where('user_id', $userId)
            ->selectRaw('relationship_type, COUNT(*) as total')->groupBy('relationship_type')->pluck('total', 'relationship_type');

        if ($counts->isEmpty()) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'title' => 'Contacts by Type',
            'labels' => $counts->keys()->map(fn ($l) => ucwords(str_replace('_', ' ', $l)))->all(),
            'datasets' => [['data' => $counts->values()->all()]],
        ];
    }
}
