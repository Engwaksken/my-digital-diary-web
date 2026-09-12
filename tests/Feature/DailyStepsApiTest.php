<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyStepsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_flutter_step_tracking_routes_load_start_sync_and_stop_today_steps(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/wellbeing/steps')
            ->assertOk()
            ->assertJsonPath('data.steps', 0)
            ->assertJsonPath('data.daily_goal', 5000)
            ->assertJsonPath('data.is_tracking', false);

        $this->postJson('/api/wellbeing/steps/start')
            ->assertOk()
            ->assertJsonPath('data.is_tracking', true);

        $this->postJson('/api/wellbeing/steps/sync', ['steps' => 125])
            ->assertOk()
            ->assertJsonPath('data.steps', 125);

        $this->postJson('/api/wellbeing/steps/stop')
            ->assertOk()
            ->assertJsonPath('data.is_tracking', false)
            ->assertJsonPath('data.steps', 125);

        $this->postJson('/api/wellbeing/steps/start')
            ->assertOk()
            ->assertJsonPath('data.is_tracking', true)
            ->assertJsonPath('data.steps', 125);
    }
}
