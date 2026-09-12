<?php

namespace Tests\Feature;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\User;
use App\Services\PeriodReviewMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TodayFocusTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_focus_includes_recurring_daily_planner_tasks(): void
    {
        $user = User::factory()->create();

        $pastPlan = DailyPlan::create([
            'user_id' => $user->id,
            'plan_date' => now()->subDays(10)->toDateString(),
            'title' => 'Recurring home plan',
        ]);

        DailyPlanItem::create([
            'daily_plan_id' => $pastPlan->id,
            'title' => 'Water the plants',
            'priority' => 'medium',
            'repeat_type' => 'daily',
            'repeat_starts_on' => now()->subDays(10)->toDateString(),
            'is_completed' => false,
        ]);

        $items = app(PeriodReviewMetricsService::class)->todayFocus($user, 6);

        $this->assertTrue(
            $items->pluck('title')->contains('Water the plants'),
            'Recurring daily tasks must appear in Today\'s Focus.'
        );
    }

    public function test_today_focus_excludes_completed_recurring_occurrence(): void
    {
        $user = User::factory()->create();

        $pastPlan = DailyPlan::create([
            'user_id' => $user->id,
            'plan_date' => now()->subDays(10)->toDateString(),
            'title' => 'Recurring home plan',
        ]);

        $item = DailyPlanItem::create([
            'daily_plan_id' => $pastPlan->id,
            'title' => 'Water the plants',
            'priority' => 'medium',
            'repeat_type' => 'daily',
            'repeat_starts_on' => now()->subDays(10)->toDateString(),
            'is_completed' => false,
        ]);

        $item->occurrences()->create([
            'user_id' => $user->id,
            'occurrence_date' => now()->toDateString(),
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $items = app(PeriodReviewMetricsService::class)->todayFocus($user, 6);

        $this->assertFalse(
            $items->pluck('title')->contains('Water the plants'),
            'Completed recurring occurrences must not appear in Today\'s Focus.'
        );
    }

    public function test_today_focus_still_includes_concrete_today_plan_items(): void
    {
        $user = User::factory()->create();

        $todayPlan = DailyPlan::create([
            'user_id' => $user->id,
            'plan_date' => now()->toDateString(),
            'title' => 'My Daily Plan',
        ]);

        DailyPlanItem::create([
            'daily_plan_id' => $todayPlan->id,
            'title' => 'Prepare interview notes',
            'priority' => 'high',
            'is_completed' => false,
        ]);

        $items = app(PeriodReviewMetricsService::class)->todayFocus($user, 6);

        $this->assertTrue(
            $items->pluck('title')->contains('Prepare interview notes'),
            'Concrete items on today\'s plan must still appear in Today\'s Focus.'
        );
    }

    public function test_api_today_focus_endpoint_includes_recurring_tasks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $pastPlan = DailyPlan::create([
            'user_id' => $user->id,
            'plan_date' => now()->subDays(10)->toDateString(),
            'title' => 'Recurring home plan',
        ]);

        DailyPlanItem::create([
            'daily_plan_id' => $pastPlan->id,
            'title' => 'Take vitamins',
            'priority' => 'medium',
            'repeat_type' => 'daily',
            'repeat_starts_on' => now()->subDays(10)->toDateString(),
            'is_completed' => false,
        ]);

        $this->getJson('/api/dashboard/today-focus')
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.repeat_type', 'daily')
            ->assertJsonPath('data.0.occurrence_date', now()->toDateString())
            ->assertJsonFragment(['title' => 'Take vitamins']);
    }
}
