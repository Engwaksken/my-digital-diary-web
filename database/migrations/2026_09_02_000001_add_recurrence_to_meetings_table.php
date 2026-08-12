<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google-Meet/Calendar-style recurrence, scoped pragmatically rather
 * than a full RRULE implementation: daily/weekly/monthly, with
 * specific weekdays for weekly, and an optional end date. Rather than
 * generating every future instance upfront (unbounded, or capped at
 * an arbitrary far-future date that still wastes rows for a meeting
 * nobody ever opens again), only a rolling window of upcoming
 * instances is ever created — see GenerateRecurringMeetings, which
 * tops this window back up daily. recurrence_parent_id links a
 * generated instance back to whichever meeting originally defined
 * the rule; only that parent row carries the rule itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('recurrence_frequency')->nullable()->after('meeting_status'); // daily, weekly, monthly
            $table->json('recurrence_days_of_week')->nullable()->after('recurrence_frequency'); // only used for weekly, e.g. [1,3,5] = Mon/Wed/Fri
            $table->date('recurrence_ends_at')->nullable()->after('recurrence_days_of_week');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_ends_at')->constrained('meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn(['recurrence_frequency', 'recurrence_days_of_week', 'recurrence_ends_at']);
        });
    }
};
