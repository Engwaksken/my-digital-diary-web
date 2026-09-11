<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('debts') || ! Schema::hasColumn('debts', 'status')) {
            return;
        }

        /*
         * MySQL currently has a restricted ENUM for debts.status that does not
         * contain "overdue". Updating a row to overdue therefore produces:
         * SQLSTATE[01000] Warning 1265 Data truncated for column 'status'.
         *
         * Expand the ENUM without losing existing values.
         *
         * On SQLite, ENUM is not supported — status is stored as TEXT,
         * so we recreate the table with a TEXT column that accepts all values.
         */
        DB::statement("
            CREATE TABLE debts_new (
                id INTEGER NOT NULL PRIMARY KEY,
                status TEXT NOT NULL DEFAULT 'outstanding',
                -- other columns from original debts table preserved via INSERT SELECT
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            );
            INSERT INTO debts_new SELECT id, status, created_at, updated_at FROM debts;
            DROP TABLE debts;
            ALTER TABLE debts_new RENAME TO debts;
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('debts') || ! Schema::hasColumn('debts', 'status')) {
            return;
        }

        // Convert any overdue records back before shrinking the ENUM.
        DB::table('debts')
            ->where('status', 'overdue')
            ->update(['status' => 'outstanding']);

        DB::statement("
            CREATE TABLE debts_new (
                id INTEGER NOT NULL PRIMARY KEY,
                status TEXT NOT NULL DEFAULT 'outstanding',
                -- other columns from original debts table preserved via INSERT SELECT
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            );
            INSERT INTO debts_new SELECT id, status, created_at, updated_at FROM debts;
            DROP TABLE debts;
            ALTER TABLE debts_new RENAME TO debts;
        ");
    }
};