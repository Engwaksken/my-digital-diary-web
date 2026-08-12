<?php

namespace App\Http\Controllers\Api;

use App\Models\ProjectTask;

class ProjectTaskController extends ApiCrudController
{
    protected string $model = ProjectTask::class;

    protected array $rules = [
        'project_id' => 'required|exists:projects,id',
        'title' => 'required|string|max:255',
        'status' => 'required|in:todo,in_progress,done',
        'due_date' => 'nullable|date',
    ];
}
