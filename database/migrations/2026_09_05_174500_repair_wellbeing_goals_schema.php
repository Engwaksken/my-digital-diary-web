<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wellbeing_goals')) {
            Schema::create('wellbeing_goals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedInteger('steps_target')->default(5000);
                $table->unsignedTinyInteger('meals_target')->default(3);
                $table->decimal('sleep_hours_target', 3, 1)->default(8.0);
                $table->unsignedSmallInteger('exercise_minutes_target')->default(30);
                $table->unsignedInteger('water_ml_target')->default(2000);
                $table->date('health_checkup_due_date')->nullable();
                $table->timestamp('health_checkup_goal_set_at')->nullable();
                $table->boolean('reminders_enabled')->default(true);
                $table->unsignedTinyInteger('reminder_interval_hours')->default(6);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('wellbeing_goals', function (Blueprint $table): void {
                if (! Schema::hasColumn('wellbeing_goals', 'steps_target')) {
                    $table->unsignedInteger('steps_target')->default(5000);
                }
                if (! Schema::hasColumn('wellbeing_goals', 'meals_target')) {
                    $table->unsignedTinyInteger('meals_target')->default(3);
                }
                if (! Schema::hasColumn('wellbeing_goals', 'sleep_hours_target')) {
                    $table->decimal('sleep_hours_target', 3, 1)->default(8.0);
                }
                if (! Schema::hasColumn('wellbeing_goals', 'exercise_minutes_target')) {
                    $table->unsignedSmallInteger('exercise_minutes_target')->default(30);
                }
                if (! Schema::hasColumn('wellbeing_goals', 'water_ml_target')) {
                    $table->unsignedInteger('water_ml_target')->default(2000);
                }
                if (! Schema::hasColumn('wellbeing_goals', 'health_checkup_due_date')) {
                    $table->date('health_checkup_due_date')->nullable();
                }
                if (! Schema::hasColumn('wellbeing_goals', 'health_checkup_goal_set_at')) {
                    $table->timestamp('health_checkup_goal_set_at')->nullable();
                }
                if (! Schema::hasColumn('wellbeing_goals', 'reminders_enabled')) {
                    $table->boolean('reminders_enabled')->default(true);
                }
                if (! Schema::hasColumn('wellbeing_goals', 'reminder_interval_hours')) {
                    $table->unsignedTinyInteger('reminder_interval_hours')->default(6);
                }
                if (! Schema::hasColumn('wellbeing_goals', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }

        if (! Schema::hasTable('wellbeing_nudge_logs')) {
            Schema::create('wellbeing_nudge_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('metric', 50);
                $table->timestamp('last_notified_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'metric']);
            });
        }

        if (Schema::hasTable('daily_wellbeing_logs')) {
            Schema::table('daily_wellbeing_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('daily_wellbeing_logs', 'last_water_logged_at')) {
                    $table->timestamp('last_water_logged_at')->nullable();
                }

                if (! Schema::hasColumn('daily_wellbeing_logs', 'last_step_activity_at')) {
                    $table->timestamp('last_step_activity_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty. This is a production repair migration and
        // should not remove user goal or wellbeing history data on rollback.
    }
};
