<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('subscription_status')
                ->constrained()->nullOnDelete();
            // Null = no active paid term yet, OR the lifetime plan (never
            // expires). For duration-based plans, set to
            // now()->addMonths($plan->duration_months) whenever a payment
            // completes — checked live in User::hasActiveAccess() rather
            // than needing a separate scheduled "expire subscriptions" job.
            $table->timestamp('subscription_expires_at')->nullable()->after('subscribed_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('payment_gateway_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_plan_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_plan_id');
            $table->dropColumn('subscription_expires_at');
        });
    }
};
