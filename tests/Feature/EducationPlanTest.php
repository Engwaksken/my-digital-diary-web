<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSubscribedOrOrganizationMember;
use App\Models\EducationPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_views_exclude_archived_education_plans(): void
    {
        $user = User::factory()->create();

        EducationPlan::create([
            'user_id' => $user->id,
            'title' => 'Active plan',
            'level' => 'course',
            'status' => 'in_progress',
            'target_completion_date' => '2026-09-15',
        ]);
        EducationPlan::create([
            'user_id' => $user->id,
            'title' => 'Archived plan',
            'level' => 'course',
            'status' => 'in_progress',
            'target_completion_date' => '2026-09-15',
            'is_archived' => true,
        ]);

        $this->withoutMiddleware(EnsureSubscribedOrOrganizationMember::class)
            ->actingAs($user)
            ->get('/education-plans?cal_month=2026-09')
            ->assertOk()
            ->assertSee('Active plan')
            ->assertDontSee('Archived plan');
    }

    public function test_api_period_filters_use_target_completion_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $matchingPlan = EducationPlan::create([
            'user_id' => $user->id,
            'title' => 'September completion',
            'level' => 'course',
            'status' => 'planned',
            'target_completion_date' => '2026-09-15',
        ]);
        EducationPlan::create([
            'user_id' => $user->id,
            'title' => 'October completion',
            'level' => 'course',
            'status' => 'planned',
            'target_completion_date' => '2026-10-15',
        ]);

        $this->getJson('/api/education-plans?period=custom&from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingPlan->id);
    }

    public function test_education_plan_show_route_is_not_registered(): void
    {
        $this->get('/education-plans/1')->assertStatus(405);
    }
}
