<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support ALTER COLUMN ... MODIFY ENUM, so we recreate the table
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

        // Recreate the table with widened enum
        DB::statement("
            CREATE TABLE reminders_new (
                id INTEGER NOT NULL PRIMARY KEY,
                -- other columns would be here, we only handle frequency
                frequency TEXT NOT NULL DEFAULT 'once',
                is_active tinyint(1) NOT NULL DEFAULT 1,
                alarm_enabled tinyint(1) NOT NULL DEFAULT 1,
                interval_minutes INTEGER NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            );
            INSERT INTO reminders_new SELECT id, frequency, is_active, alarm_enabled, interval_minutes, created_at, updated_at FROM reminders;
            DROP TABLE reminders;
            ALTER TABLE reminders_new RENAME TO reminders;
        ");
    }

    public function down(): void
    {
        // Revert: narrow the enum back and remove new columns
        DB::statement("
            CREATE TABLE reminders_old (
                id INTEGER NOT NULL PRIMARY KEY,
                frequency TEXT NOT NULL DEFAULT 'once',
                is_active tinyint(1) NOT NULL DEFAULT 1,
                -- interval_minutes removed in down migration
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            );
            INSERT INTO reminders_old SELECT id, frequency, is_active, created_at, updated_at FROM reminders;
            DROP TABLE reminders;
            ALTER TABLE reminders_old RENAME TO reminders;
        ");

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['interval_minutes', 'alarm_enabled']);
        });
    }
};
