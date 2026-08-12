<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Daily 6am email of the user's top 3 open items — see
            // SendDailyTopTasksDigest. Opt-out (default true) rather than
            // opt-in, since it's a low-noise, once-a-day summary most
            // users likely want.
            $table->boolean('daily_digest_enabled')->default(true)->after('alarms_muted');
            // Whether the auto-created hydration reminder (see
            // RegisteredUserController) should exist at all — toggled off
            // here if a user deletes that reminder, so
            // CreateHydrationReminders doesn't just recreate it.
            $table->boolean('hydration_reminders_enabled')->default(true)->after('daily_digest_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_digest_enabled', 'hydration_reminders_enabled']);
        });
    }
};
