<?php

namespace Tests\Feature;

use App\Models\IoTecSubscriptionTransaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionPaymentActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionPaymentActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_iotec_activation_creates_completed_payment_and_paid_invoice(): void
    {
        $user = User::factory()->create();

        $plan = SubscriptionPlan::create([
            'key' => 'test-monthly',
            'name' => 'Test Monthly',
            'duration_months' => 1,
            'discount_percent' => 0,
            'flat_price' => 100,
            'is_enabled' => true,
            'sort_order' => 99,
        ]);

        $externalId = (string) Str::uuid();

        $transaction = IoTecSubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'external_id' => $externalId,
            'payment_channel' => 'mobile_money',
            'payer' => '256700000000',
            'amount' => 100,
            'currency' => 'UGX',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        app(SubscriptionPaymentActivationService::class)->activateFromIoTec($transaction);

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'method' => 'mobile_money',
            'status' => 'completed',
            'reference' => $externalId,
            'gateway_transaction_id' => $externalId,
        ]);

        $paymentId = $transaction->fresh()->payment_id;

        $this->assertNotNull($paymentId);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'payment_id' => $paymentId,
            'status' => 'paid',
        ]);
    }
}
