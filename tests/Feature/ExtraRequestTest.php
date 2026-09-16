<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserExtraRequest;
use App\Services\ApplyExtraRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtraRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_extra_recording_quota_request(): void
    {
        $user = User::factory()->create([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->post(route('extra-requests.store'), [
            'quota_amount' => 120,
            'amount' => 9000,
            'currency' => 'UGX',
            'description' => 'Extra meeting recording minutes.',
        ]);

        $request = UserExtraRequest::firstOrFail();

        $response->assertRedirect(route('extra-requests.show', $request));
        $this->assertDatabaseHas('user_extra_requests', [
            'user_id' => $user->id,
            'quota_amount' => 120,
            'amount' => 9000,
            'currency' => 'UGX',
            'status' => 'pending',
        ]);
    }

    public function test_applying_extra_request_adds_remaining_minutes_and_casts_expiry(): void
    {
        $user = User::factory()->create([
            'extra_recording_quota_minutes' => 30,
            'extra_quota_expires_at' => now()->addDays(5),
        ]);
        $extraRequest = UserExtraRequest::factory()->for($user)->create([
            'quota_amount' => 120,
            'quota_used' => 20,
            'expires_at' => now()->addDays(30),
        ]);

        app(ApplyExtraRequestService::class)->applyQuota($extraRequest);

        $user->refresh();
        $this->assertSame(130, $user->extra_recording_quota_minutes);
        $this->assertTrue($user->extra_quota_expires_at->isSameDay($extraRequest->expires_at));
        $this->assertSame('applied', $extraRequest->fresh()->status);
    }
}
