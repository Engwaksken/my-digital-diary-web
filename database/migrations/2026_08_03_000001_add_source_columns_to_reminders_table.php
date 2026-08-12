<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a reminder record where it came from (e.g. an auto-generated
 * "checkup coming up" reminder created from a HealthCheckup row) so
 * scheduled generator commands can avoid creating duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            // Fingerprints *what* the reminder was generated for, e.g. "42:2026-09-01".
            // If the source record's relevant date changes, the signature changes
            // too, so a fresh reminder is generated instead of silently reusing
            // a stale one.
            $table->string('source_signature')->nullable()->after('source_id');

            $table->index(['source_type', 'source_id', 'source_signature'], 'reminders_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex('reminders_source_index');
            $table->dropColumn(['source_type', 'source_id', 'source_signature']);
        });
    }
};
