<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'external_platform')) {
                $table->string('external_platform')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'external_id')) {
                $table->string('external_id')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'meeting_status')) {
                $table->string('meeting_status')->default('scheduled');
            }

            if (! Schema::hasColumn('meetings', 'is_archived')) {
                $table->boolean('is_archived')->default(false);
            }

            if (! Schema::hasColumn('meetings', 'calendar_provider')) {
                $table->string('calendar_provider', 40)->nullable();
            }

            if (! Schema::hasColumn('meetings', 'external_calendar_id')) {
                $table->string('external_calendar_id')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'external_event_id')) {
                $table->string('external_event_id')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'external_series_id')) {
                $table->string('external_series_id')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'calendar_synced_at')) {
                $table->timestamp('calendar_synced_at')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'calendar_sync_from_date')) {
                $table->date('calendar_sync_from_date')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'calendar_sync_to_date')) {
                $table->date('calendar_sync_to_date')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'recurrence_frequency')) {
                $table->string('recurrence_frequency')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'recurrence_days_of_week')) {
                $table->json('recurrence_days_of_week')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'recurrence_ends_at')) {
                $table->date('recurrence_ends_at')->nullable();
            }

            if (! Schema::hasColumn('meetings', 'recurrence_parent_id')) {
                $table->unsignedBigInteger('recurrence_parent_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // Repair migration only; do not remove data-bearing columns on rollback.
    }
};
