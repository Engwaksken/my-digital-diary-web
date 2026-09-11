<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_wellbeing_logs')) {
            Schema::table('daily_wellbeing_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('daily_wellbeing_logs', 'energy_level')) {
                    $table->unsignedTinyInteger('energy_level')->nullable()->after('mood');
                }
                if (! Schema::hasColumn('daily_wellbeing_logs', 'stress_level')) {
                    $table->unsignedTinyInteger('stress_level')->nullable()->after('energy_level');
                }
                if (! Schema::hasColumn('daily_wellbeing_logs', 'pain_level')) {
                    $table->unsignedTinyInteger('pain_level')->nullable()->after('stress_level');
                }
                if (! Schema::hasColumn('daily_wellbeing_logs', 'wellbeing_score')) {
                    $table->unsignedTinyInteger('wellbeing_score')->nullable()->after('pain_level');
                }
                if (! Schema::hasColumn('daily_wellbeing_logs', 'symptoms')) {
                    $table->text('symptoms')->nullable()->after('wellbeing_score');
                }
            });
        }

        if (Schema::hasTable('health_checkups')) {
            Schema::table('health_checkups', function (Blueprint $table): void {
                if (! Schema::hasColumn('health_checkups', 'weight_kg')) {
                    $table->decimal('weight_kg', 6, 2)->nullable()->after('doctor_name');
                }
                if (! Schema::hasColumn('health_checkups', 'blood_pressure_systolic')) {
                    $table->unsignedSmallInteger('blood_pressure_systolic')->nullable()->after('weight_kg');
                }
                if (! Schema::hasColumn('health_checkups', 'blood_pressure_diastolic')) {
                    $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable()->after('blood_pressure_systolic');
                }
                if (! Schema::hasColumn('health_checkups', 'heart_rate_bpm')) {
                    $table->unsignedSmallInteger('heart_rate_bpm')->nullable()->after('blood_pressure_diastolic');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('daily_wellbeing_logs')) {
            Schema::table('daily_wellbeing_logs', function (Blueprint $table): void {
                foreach (['energy_level','stress_level','pain_level','wellbeing_score','symptoms'] as $column) {
                    if (Schema::hasColumn('daily_wellbeing_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('health_checkups')) {
            Schema::table('health_checkups', function (Blueprint $table): void {
                foreach (['weight_kg','blood_pressure_systolic','blood_pressure_diastolic','heart_rate_bpm'] as $column) {
                    if (Schema::hasColumn('health_checkups', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
