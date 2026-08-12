<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a meeting as having been FETCHED from an external platform
 * (rather than created manually in-app), and records that platform's own
 * event ID — the dedup key: if the same meeting is fetched again (e.g.
 * because it also matches a second linked email), external_id +
 * external_platform is checked before inserting a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('external_platform')->nullable()->after('status');
            $table->string('external_id')->nullable()->after('external_platform');
            $table->string('meeting_status')->default('scheduled')->after('external_id')
                ->comment('scheduled, completed, cancelled, missed — separate from the existing status column\'s simpler scheduled/completed/cancelled, adding "missed" (past-due, never marked completed)');

            $table->index(['external_platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['external_platform', 'external_id', 'meeting_status']);
        });
    }
};
