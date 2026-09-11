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
                if (Schema::hasColumn('daily_wellbeing_logs', 'water_ml')) {
                    $table->unsignedInteger('water_ml')->default(0)->change();
                }

                if (Schema::hasColumn('daily_wellbeing_logs', 'water_target_ml')) {
                    $table->unsignedInteger('water_target_ml')->default(2000)->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Safe defaults intentionally retained.
    }
};
