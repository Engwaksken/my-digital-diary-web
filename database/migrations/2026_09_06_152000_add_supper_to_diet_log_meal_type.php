<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diet_logs')
            || ! Schema::hasColumn('diet_logs', 'meal_type')) {
            return;
        }

        // SQLite stores Laravel enum columns as TEXT, so it already accepts
        // the new value and has no information_schema catalog to inspect.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $database = DB::getDatabaseName();

        $column = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'diet_logs')
            ->where('COLUMN_NAME', 'meal_type')
            ->first(['DATA_TYPE', 'COLUMN_TYPE']);

        if (! $column || strtolower((string) $column->DATA_TYPE) !== 'enum') {
            // Column is not an ENUM (e.g. already TEXT on SQLite).
            // On SQLite, meal_type is TEXT and 'supper' is a valid value,
            // so there's nothing to do — the migration is a no-op.
            return;
        }

        DB::statement("ALTER TABLE diet_logs MODIFY COLUMN meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack', 'supper') NOT NULL");
    }

    public function down(): void
    {
        // Supper is intentionally retained to avoid invalidating user data.
    }
};
