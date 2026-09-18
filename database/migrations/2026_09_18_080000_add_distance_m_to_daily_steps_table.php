<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('daily_steps')
            && ! Schema::hasColumn('daily_steps', 'distance_m')
        ) {
            Schema::table('daily_steps', function (Blueprint $table): void {
                $table->unsignedBigInteger('distance_m')->nullable()->after('steps');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('daily_steps')
            && Schema::hasColumn('daily_steps', 'distance_m')
        ) {
            Schema::table('daily_steps', function (Blueprint $table): void {
                $table->dropColumn('distance_m');
            });
        }
    }
};