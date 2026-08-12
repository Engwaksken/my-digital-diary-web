<?php

namespace App\Http\Controllers\Api;

use App\Models\PersonalRelationship;

class RelationshipController extends ApiCrudController
{
    protected string $model = PersonalRelationship::class;

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
}
