<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'calendar_provider')) {
                $table->string('calendar_provider', 40)->nullable()->index();
            }
            if (!Schema::hasColumn('meetings', 'external_calendar_id')) {
                $table->string('external_calendar_id')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'external_event_id')) {
                $table->string('external_event_id')->nullable()->index();
            }
            if (!Schema::hasColumn('meetings', 'external_series_id')) {
                $table->string('external_series_id')->nullable()->index();
            }
            if (!Schema::hasColumn('meetings', 'calendar_synced_at')) {
                $table->timestamp('calendar_synced_at')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'calendar_sync_from_date')) {
                $table->date('calendar_sync_from_date')->nullable();
            }
            if (!Schema::hasColumn('meetings', 'calendar_sync_to_date')) {
                $table->date('calendar_sync_to_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            foreach ([
                'calendar_provider','external_calendar_id','external_event_id',
                'external_series_id','calendar_synced_at',
                'calendar_sync_from_date','calendar_sync_to_date',
            ] as $column) {
                if (Schema::hasColumn('meetings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
