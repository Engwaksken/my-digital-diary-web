<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iotec_subscription_transactions')) {
            Schema::create('iotec_subscription_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('subscription_plan_id')->nullable()->index();
                $table->uuid('external_id')->unique();
                $table->string('iotec_request_id', 120)->nullable()->unique();
                $table->enum('payment_channel', ['mobile_money', 'card']);
                $table->string('card_brand', 30)->nullable();
                $table->string('payer', 255);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 8)->default('UGX');
                $table->string('status', 40)->default('pending')->index();
                $table->string('status_code', 120)->nullable();
                $table->text('status_message')->nullable();
                $table->text('card_redirect_url')->nullable();
                $table->json('gateway_response')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'subscription_status')) {
                $table->string('subscription_status', 30)->default('trial')->index();
            }
            if (! Schema::hasColumn('users', 'subscription_plan_id')) {
                $table->unsignedBigInteger('subscription_plan_id')->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'subscription_started_at')) {
                $table->timestamp('subscription_started_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iotec_subscription_transactions');
    }
};
