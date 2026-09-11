<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentsBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_delete_only_failed_or_rejected_payments_older_than_five_days(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $oldFailed = Payment::forceCreate([
            'user_id' => $user->id,
            'method' => 'mobile_money',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'failed',
            'reference' => 'old-failed',
            'created_at' => now()->subDays(6),
            'updated_at' => now()->subDays(6),
        ]);

        $oldRejected = Payment::forceCreate([
            'user_id' => $user->id,
            'method' => 'mobile_money',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'rejected',
            'reference' => 'old-rejected',
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(7),
        ]);

        $recentFailed = Payment::forceCreate([
            'user_id' => $user->id,
            'method' => 'mobile_money',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'failed',
            'reference' => 'recent-failed',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $completed = Payment::forceCreate([
            'user_id' => $user->id,
            'method' => 'mobile_money',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'completed',
            'reference' => 'completed',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.payments.bulk-destroy'), [
            'payment_ids' => [
                $oldFailed->id,
                $oldRejected->id,
                $recentFailed->id,
                $completed->id,
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('payments', ['id' => $oldFailed->id]);
        $this->assertDatabaseMissing('payments', ['id' => $oldRejected->id]);
        $this->assertDatabaseHas('payments', ['id' => $recentFailed->id]);
        $this->assertDatabaseHas('payments', ['id' => $completed->id]);
    }
}
