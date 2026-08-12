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
            $table->timestamp('trial_ends_at')->nullable()->after('email_verified_at');
            $table->enum('subscription_status', ['trialing', 'active', 'canceled', 'expired'])
                ->default('trialing')
                ->after('trial_ends_at');
            $table->timestamp('subscribed_at')->nullable()->after('subscription_status');
        });

        // Give any users that already exist a full month from right now,
        // so nobody already using the app gets locked out immediately.
        DB::table('users')->whereNull('trial_ends_at')->update([
            'trial_ends_at' => now()->addMonth(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trial_ends_at', 'subscription_status', 'subscribed_at']);
        });
    }
};
