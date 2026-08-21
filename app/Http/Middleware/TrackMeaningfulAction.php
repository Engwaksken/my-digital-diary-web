<?php

namespace App\Http\Middleware;

use App\Services\DailyEngagementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackMeaningfulAction
{
    public function __construct(
        private readonly DailyEngagementService $engagement
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->user() || $response->getStatusCode() >= 400) {
            return $response;
        }

        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH'], true)) {
            return $response;
        }

        $event = $this->resolveEvent($request);

        if ($event === null) {
            return $response;
        }

        [$eventType, $sourceType] = $event;

        try {
            $this->engagement->markMeaningfulAction(
                $request->user(),
                $eventType,
                $sourceType,
                $this->sourceId($request),
                [
                    'route' => optional($request->route())->getName(),
                    'path' => $request->path(),
                ]
            );
        } catch (\Throwable $e) {
            // Engagement tracking must never break the user's actual save.
            report($e);
        }

        return $response;
    }

    private function resolveEvent(Request $request): ?array
    {
        $route = strtolower((string) optional($request->route())->getName());
        $path = strtolower($request->path());
        $needle = trim($route . ' ' . $path);

        if ($this->contains($needle, ['daily-planner', 'daily_planner'])) {
            $status = strtolower((string) $request->input('status', ''));
            $completed = $request->boolean('completed')
                || $request->boolean('is_completed')
                || in_array($status, ['completed', 'done'], true);

            return [
                $completed ? 'task_completed' : 'day_planned',
                'daily_planner',
            ];
        }

        if ($this->contains($needle, ['expense', 'expenses'])) {
            return ['expense_recorded', 'expense'];
        }

        if ($this->contains($needle, [
            'saving', 'savings', 'savings-goal', 'savings_goal',
            'contribution', 'contributions'
        ])) {
            return ['savings_added', 'savings'];
        }

        if ($this->contains($needle, [
            'health-checkup', 'health_checkup', 'wellbeing',
            'exercise-log', 'exercise_log', 'exercise'
        ])) {
            return ['health_logged', 'health'];
        }

        if ($this->contains($needle, [
            'personal-goal', 'personal_goal', 'goal-intelligence', 'goals'
        ])) {
            $status = strtolower((string) $request->input('status', ''));
            return [
                in_array($status, ['completed', 'achieved', 'done'], true)
                    ? 'goal_milestone_completed'
                    : 'goal_updated',
                'goal',
            ];
        }

        if ($this->contains($needle, ['meeting', 'meetings'])) {
            if ($this->contains($needle, [
                'summary', 'notes', 'review', 'transcript', 'recording'
            ])) {
                return ['meeting_reviewed', 'meeting'];
            }
            return ['meeting_saved', 'meeting'];
        }

        if ($this->contains($needle, [
            'spiritual-practice', 'spiritual_practice',
            'spiritual-growth', 'spiritual_growth'
        ])) {
            return ['spiritual_reflection_added', 'spiritual_practice'];
        }

        return null;
    }

    private function sourceId(Request $request): ?int
    {
        foreach ([
            'id',
            'item',
            'expense',
            'meeting',
            'goal',
            'health_checkup',
            'spiritual_practice',
        ] as $key) {
            $routeValue = $request->route($key);

            if (is_numeric($routeValue)) {
                return (int) $routeValue;
            }

            if (is_object($routeValue) && isset($routeValue->id)) {
                return (int) $routeValue->id;
            }
        }

        $requestId = $request->input('id');

        return is_numeric($requestId) ? (int) $requestId : null;
    }

    private function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
