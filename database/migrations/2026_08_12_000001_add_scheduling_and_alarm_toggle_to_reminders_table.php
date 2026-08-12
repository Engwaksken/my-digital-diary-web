<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original enum only supported once/daily/weekly/monthly/annually
        // — no way to repeat hourly or every N minutes. Widening it requires
        // a raw MODIFY COLUMN on MySQL (Schema::table()->enum() can't alter
        // an existing enum's allowed values in place).
        DB::statement("
            ALTER TABLE reminders
            MODIFY COLUMN frequency ENUM('once', 'every_n_minutes', 'hourly', 'daily', 'weekly', 'monthly', 'annually')
            NOT NULL DEFAULT 'once'
        ");

        Schema::table('reminders', function (Blueprint $table) {
            // Only used when frequency = 'every_n_minutes'.
            $table->unsignedInteger('interval_minutes')->nullable()->after('frequency');

            // Lets a user turn the in-app popup+sound alarm on/off per
            // reminder, independent of whether the reminder is still
            // active at all (is_active) or which channel (mail/database)
            // it uses. A reminder can keep emailing without ever popping
            // up an alarm, or vice versa.
            $table->boolean('alarm_enabled')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['interval_minutes', 'alarm_enabled']);
        });

        DB::statement("
            ALTER TABLE reminders
            MODIFY COLUMN frequency ENUM('once', 'daily', 'weekly', 'monthly', 'annually')
            NOT NULL DEFAULT 'once'
        ");
    }
};
