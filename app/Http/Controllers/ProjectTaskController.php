<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PersonalGoal;
use Illuminate\Http\Request;

class ProjectTaskController extends CrudController
{
    protected string $model = ProjectTask::class;
    protected string $routeName = 'project-tasks';
    protected string $title = 'Task';
    protected string $icon = 'fa-solid fa-clipboard-check';
    protected string $accent = 'cyan';

    protected array $fields = [
        ['name' => 'project_id', 'label' => 'Project', 'type' => 'select', 'required' => true],
        ['name' => 'personal_goal_id', 'label' => 'Linked Goal', 'type' => 'select'],
        ['name' => 'title', 'label' => 'Task', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Buy paint'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => [
            'todo' => 'To Do', 'in_progress' => 'In Progress', 'done' => 'Done',
        ]],
        ['name' => 'due_date', 'label' => 'Due Date', 'type' => 'date'],
    ];

    protected array $rules = [
        'project_id' => 'required|exists:projects,id',
        'personal_goal_id' => 'nullable|exists:personal_goals,id',
        'title' => 'required|string|max:255',
        'status' => 'required|in:todo,in_progress,done',
        'due_date' => 'nullable|date',
    ];

    /** Populate the "project" select options with the user's own projects. */
    private function withProjectOptions(Request $request): array
    {
        $fields = $this->fields;
        $projects = Project::where('user_id', $request->user()->id)->pluck('name', 'id')->toArray();
        $fields[0]['options'] = $projects;
        $goals = PersonalGoal::where('user_id', $request->user()->id)->where('is_archived',false)->whereIn('status',['not_started','in_progress'])->pluck('title','id')->toArray();
        $fields[1]['options'] = $goals;

        return $fields;
    }

    public function index(Request $request)
    {
        $query = ProjectTask::where('user_id', $request->user()->id)->with('project');

        return $this->renderIndex($request, $query, ['fields' => $this->withProjectOptions($request)]);
    }

    protected function stats(Request $request): array
    {
        $userId = $request->user()->id;
        $base = ProjectTask::where('user_id', $userId);

        return [
            ['label' => 'To do', 'value' => (string) (clone $base)->where('status', 'todo')->count(), 'icon' => 'fa-solid fa-list-check', 'color' => 'slate'],
            ['label' => 'In progress', 'value' => (string) (clone $base)->where('status', 'in_progress')->count(), 'icon' => 'fa-solid fa-spinner', 'color' => 'blue'],
            ['label' => 'Done', 'value' => (string) (clone $base)->where('status', 'done')->count(), 'icon' => 'fa-solid fa-circle-check', 'color' => 'emerald'],
        ];
    }

    protected function chart(Request $request): ?array
    {
        $userId = $request->user()->id;
        $counts = ProjectTask::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->isEmpty()) {
            return null;
        }

        $labels = ['todo', 'in_progress', 'done'];

        return [
            'type' => 'doughnut',
            'title' => 'Tasks by Status',
            'labels' => array_map(fn ($l) => ucwords(str_replace('_', ' ', $l)), $labels),
            'datasets' => [['data' => array_map(fn ($l) => (int) ($counts[$l] ?? 0), $labels)]],
        ];
    }

    public function create(Request $request)
    {
        return view('crud.form', [
            'item' => new ProjectTask,
            'fields' => $this->withProjectOptions($request),
            'title' => $this->title,
            'routeName' => $this->routeName,
            'icon' => $this->icon,
            'accent' => $this->accent,
        ]);
    }

    public function edit(Request $request, int $id)
    {
        $item = ProjectTask::where('user_id', $request->user()->id)->findOrFail($id);

        return view('crud.form', [
            'item' => $item,
            'fields' => $this->withProjectOptions($request),
            'title' => $this->title,
            'routeName' => $this->routeName,
            'icon' => $this->icon,
            'accent' => $this->accent,
        ]);
    }
}
