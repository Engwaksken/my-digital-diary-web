<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'subscription_status')) {
                $table->string('subscription_status', 30)
                    ->default('trial')
                    ->index();
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

            if (! Schema::hasColumn('users', 'subscription_plan_id')) {
                $table->unsignedBigInteger('subscription_plan_id')->nullable()->index();
            }
        });

        /*
         * Normalise old mixed-case values so every part of the application
         * reads the same canonical state.
         */
        DB::table('users')
            ->whereNotNull('subscription_status')
            ->update([
                'subscription_status' => DB::raw('LOWER(TRIM(subscription_status))'),
            ]);

        /*
         * Any user already marked active must not continue to be considered
         * a free-trial user.
         */
        DB::table('users')
            ->whereRaw("LOWER(TRIM(COALESCE(subscription_status, ''))) = 'active'")
            ->update([
                'trial_ends_at' => null,
            ]);
    }

    public function down(): void
    {
        // Intentionally non-destructive. Subscription history should not be
        // removed during rollback.
    }
};
