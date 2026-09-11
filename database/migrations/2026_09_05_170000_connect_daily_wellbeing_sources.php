<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_wellbeing_logs')) {
            return;
        }

        Schema::table('daily_wellbeing_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('daily_wellbeing_logs', 'meals_logged')) {
                $table->unsignedSmallInteger('meals_logged')->default(0)->after('steps');
            }

            if (! Schema::hasColumn('daily_wellbeing_logs', 'calories_logged')) {
                $table->unsignedInteger('calories_logged')->default(0)->after('meals_logged');
            }

            if (! Schema::hasColumn('daily_wellbeing_logs', 'daily_food_entries')) {
                $table->unsignedSmallInteger('daily_food_entries')->default(0)->after('calories_logged');
            }

            if (! Schema::hasColumn('daily_wellbeing_logs', 'sleep_minutes')) {
                $table->unsignedSmallInteger('sleep_minutes')->default(0)->after('daily_food_entries');
            }

            if (! Schema::hasColumn('daily_wellbeing_logs', 'sleep_quality')) {
                $table->string('sleep_quality', 30)->nullable()->after('sleep_minutes');
            }

            if (! Schema::hasColumn('daily_wellbeing_logs', 'exercise_sessions')) {
                $table->unsignedSmallInteger('exercise_sessions')->default(0)->after('exercise_minutes');
            }

            if (! Schema::hasColumn('daily_wellbeing_logs', 'source_synced_at')) {
                $table->timestamp('source_synced_at')->nullable()->after('exercise_sessions');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('daily_wellbeing_logs')) {
            return;
        }

        Schema::table('daily_wellbeing_logs', function (Blueprint $table): void {
            foreach ([
                'meals_logged',
                'calories_logged',
                'daily_food_entries',
                'sleep_minutes',
                'sleep_quality',
                'exercise_sessions',
                'source_synced_at',
            ] as $column) {
                if (Schema::hasColumn('daily_wellbeing_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
