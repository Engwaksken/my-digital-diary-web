<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens checkup_date/next_due_date from DATE to DATETIME — health
 * checkups often have a specific appointment TIME (e.g. "Dentist at
 * 2:00 PM"), not just a day. Raw ALTER TABLE (not Schema::table()) since
 * changing a column's underlying TYPE, not just adding/dropping one,
 * needs MODIFY COLUMN on MySQL. Existing values keep their date and get
 * midnight (00:00:00) as their time — nothing is lost, there was no time
 * information to preserve.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE health_checkups_new (
                    id INTEGER NOT NULL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    checkup_type TEXT NOT NULL,
                    checkup_date TEXT NOT NULL,
                    doctor_name TEXT NULL,
                    findings TEXT NULL,
                    next_due_date TEXT,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    CONSTRAINT fk_health_checkups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );
                INSERT INTO health_checkups_new SELECT id, user_id, checkup_type, checkup_date, doctor_name, findings, next_due_date, created_at, updated_at FROM health_checkups;
                DROP TABLE health_checkups;
                ALTER TABLE health_checkups_new RENAME TO health_checkups;
            ");
        } else {
            DB::statement('ALTER TABLE health_checkups MODIFY checkup_date DATETIME NOT NULL, MODIFY next_due_date DATETIME NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE health_checkups_new (
                    id INTEGER NOT NULL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    checkup_type TEXT NOT NULL,
                    checkup_date TEXT NOT NULL,
                    doctor_name TEXT NULL,
                    findings TEXT NULL,
                    next_due_date TEXT,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    CONSTRAINT fk_health_checkups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );
                INSERT INTO health_checkups_new SELECT id, user_id, checkup_type, checkup_date, doctor_name, findings, next_due_date, created_at, updated_at FROM health_checkups;
                DROP TABLE health_checkups;
                ALTER TABLE health_checkups_new RENAME TO health_checkups;
            ");
        } else {
            DB::statement('ALTER TABLE health_checkups MODIFY checkup_date DATE NOT NULL, MODIFY next_due_date DATE NULL');
        }
    }
};