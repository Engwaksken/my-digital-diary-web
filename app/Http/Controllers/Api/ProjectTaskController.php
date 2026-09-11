<?php

namespace App\Http\Controllers\Api;

use App\Models\ProjectTask;
use App\Services\ProjectTaskReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends ApiCrudController
{
    protected string $model = ProjectTask::class;

    protected array $rules = [
        'project_id' => 'required|exists:projects,id',
        'personal_goal_id' => 'nullable|exists:personal_goals,id',
        'title' => 'required|string|max:255',
        'status' => 'required|in:todo,in_progress,done,completed',
        'progress_percent' => 'nullable|integer|min:0|max:100',
        'due_date' => 'nullable|date',
        'due_time' => 'nullable|date_format:H:i',
        'reminder_enabled' => 'nullable|boolean',
        'reminder_offset_minutes' => 'nullable|integer|in:0,5,15,30,60,120,1440',
        'reminder_custom_at' => 'nullable|date',
        'reminder_channel' => 'nullable|in:in_app,push,email',
    ];


    public function store(Request $request): JsonResponse
    {
        $data = $this->normalise($request->validate($this->rules));
        $data['user_id'] = $request->user()->id;
        $item = ProjectTask::create($data);
        app(ProjectTaskReminderService::class)->sync($item, $request->user());
        return response()->json($item->fresh(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = ProjectTask::where('user_id', $request->user()->id)->findOrFail($id);
        $item->update(
            $this->normalise($request->validate($this->rules))
        );
        app(ProjectTaskReminderService::class)->sync($item->fresh(), $request->user());
        return response()->json($item->fresh());
    }

    private function normalise(array $data): array
    {
        if (($data['status'] ?? '') === 'completed') {
            $data['status'] = 'done';
            $data['progress_percent'] = 100;
        }

        if (($data['status'] ?? '') === 'done') {
            $data['progress_percent'] = 100;
        }

        $data['progress_percent'] = max(
            0,
            min(100, (int) ($data['progress_percent'] ?? 0))
        );

        return $data;
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = ProjectTask::where('user_id', $request->user()->id)->findOrFail($id);
        app(ProjectTaskReminderService::class)->cancel($item, $request->user());
        $item->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
