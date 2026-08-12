<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;

class ProjectController extends ApiCrudController
{
    protected string $model = Project::class;

    protected array $rules = [
        'name' => 'required|string|max:255',
        'status' => 'required|in:planned,in_progress,on_hold,completed',
        'start_date' => 'nullable|date',
        'deadline' => 'nullable|date',
        'description' => 'nullable|string',
    ];
}
