<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DietLog;
use App\Services\DailyWellbeingSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DietLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = DietLog::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->paginate(max(1, min(50, (int) $request->integer('per_page', 10))))
            ->withQueryString();

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->owned($request, $id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->payload($request);

        $item = DietLog::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        $this->syncWellbeing($request, $item);

        return response()->json([
            'message' => 'Meal saved.',
            'data' => $item->fresh(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->owned($request, $id);
        $item->update($this->payload($request, $item));

        $this->syncWellbeing($request, $item);

        return response()->json([
            'message' => 'Meal updated.',
            'data' => $item->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = $this->owned($request, $id);
        $date = optional($item->logged_at)->toDateString();

        $item->delete();

        if ($date) {
            app(DailyWellbeingSyncService::class)
                ->sync($request->user(), $date);
        }

        return response()->json(['message' => 'Meal deleted.']);
    }

    private function payload(Request $request, ?DietLog $existing = null): array
    {
        $timezone = $request->user()->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $food = trim((string) (
            $request->input('food_items')
            ?? $request->input('food')
            ?? $request->input('description')
            ?? $request->input('title')
            ?? $existing?->food_items
            ?? ''
        ));

        $mealType = strtolower(trim((string) (
            $request->input('meal_type')
            ?? $existing?->meal_type
            ?? ''
        )));

        if (! in_array($mealType, ['breakfast', 'lunch', 'snack', 'dinner', 'supper'], true)) {
            $hour = now($timezone)->hour;

            $mealType = match (true) {
                $hour < 11 => 'breakfast',
                $hour < 15 => 'lunch',
                $hour < 18 => 'snack',
                $hour < 21 => 'dinner',
                default => 'supper',
            };
        }

        $data = [
            'meal_type' => $mealType,
            'food_items' => $food,
            'logged_at' => $request->input('logged_at')
                ?? optional($existing?->logged_at)->toDateString()
                ?? now($timezone)->toDateString(),
            'calories' => $request->has('calories')
                ? $request->input('calories')
                : $existing?->calories,
            'notes' => $request->has('notes')
                ? $request->input('notes')
                : $existing?->notes,
            'is_archived' => $request->has('is_archived')
                ? $request->boolean('is_archived')
                : (bool) ($existing?->is_archived ?? false),
        ];

        return Validator::make($data, [
            'meal_type' => ['required', 'in:breakfast,lunch,snack,dinner,supper'],
            'food_items' => ['required', 'string', 'max:4000'],
            'logged_at' => ['required', 'date'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_archived' => ['boolean'],
        ])->validate();
    }

    private function syncWellbeing(Request $request, DietLog $item): void
    {
        app(DailyWellbeingSyncService::class)->sync(
            $request->user(),
            optional($item->logged_at)->toDateString()
                ?? now()->toDateString()
        );
    }

    private function owned(Request $request, int $id): DietLog
    {
        return DietLog::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }
}
