<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_plan_items')) {
            return;
        }

        Schema::table('daily_plan_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('daily_plan_items', 'reminder_enabled')) {
                $table->boolean('reminder_enabled')->default(false)->after('end_time');
            }
            if (! Schema::hasColumn('daily_plan_items', 'reminder_offset_minutes')) {
                $table->integer('reminder_offset_minutes')->nullable()->after('reminder_enabled');
            }
            if (! Schema::hasColumn('daily_plan_items', 'reminder_custom_at')) {
                $table->dateTime('reminder_custom_at')->nullable()->after('reminder_offset_minutes');
            }
            if (! Schema::hasColumn('daily_plan_items', 'reminder_channels')) {
                $table->json('reminder_channels')->nullable()->after('reminder_custom_at');
            }
            if (! Schema::hasColumn('daily_plan_items', 'reminder_id')) {
                $table->unsignedBigInteger('reminder_id')->nullable()->after('reminder_channels')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('daily_plan_items')) {
            return;
        }

        Schema::table('daily_plan_items', function (Blueprint $table): void {
            foreach ([
                'reminder_id',
                'reminder_channels',
                'reminder_custom_at',
                'reminder_offset_minutes',
                'reminder_enabled',
            ] as $column) {
                if (Schema::hasColumn('daily_plan_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
