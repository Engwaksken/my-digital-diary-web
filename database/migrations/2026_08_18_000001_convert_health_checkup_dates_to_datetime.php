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
        DB::statement('ALTER TABLE health_checkups MODIFY COLUMN checkup_date DATETIME NOT NULL');
        DB::statement('ALTER TABLE health_checkups MODIFY COLUMN next_due_date DATETIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE health_checkups MODIFY COLUMN checkup_date DATE NOT NULL');
        DB::statement('ALTER TABLE health_checkups MODIFY COLUMN next_due_date DATE NULL');
    }
};
