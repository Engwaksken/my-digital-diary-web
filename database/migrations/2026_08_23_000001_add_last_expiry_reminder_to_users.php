<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which "days remaining" milestone (14, 12, 10, 8, 6, 4, 2, 0) a
 * user was last emailed a subscription-expiry reminder for, so the daily
 * scheduled command never sends the same milestone's email twice —
 * without this, a command that (for whatever reason) runs more than once
 * on the same day would re-send the same reminder repeatedly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('last_expiry_reminder_days')->nullable()->after('subscription_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_expiry_reminder_days');
        });
    }
};
