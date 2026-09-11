<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['diet_logs', 'exercise_logs', 'sleep_logs'] as $tableName) {
            if (! Schema::hasTable($tableName)
                || Schema::hasColumn($tableName, 'annual_plan_source_key')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('annual_plan_source_key', 191)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['diet_logs', 'exercise_logs', 'sleep_logs'] as $tableName) {
            if (! Schema::hasTable($tableName)
                || ! Schema::hasColumn($tableName, 'annual_plan_source_key')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('annual_plan_source_key');
            });
        }
    }
};
