<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['exercise_logs', 'diet_logs', 'sleep_logs'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'daily_plan_source_key')) {
                    $table->string('daily_plan_source_key', 120)->nullable()->unique();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['exercise_logs', 'diet_logs', 'sleep_logs'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'daily_plan_source_key')) {
                    $table->dropColumn('daily_plan_source_key');
                }
            });
        }
    }
};
