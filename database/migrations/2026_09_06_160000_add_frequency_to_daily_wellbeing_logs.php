<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_wellbeing_logs')) {
            return;
        }

        Schema::table('daily_wellbeing_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('daily_wellbeing_logs', 'entry_frequency')) {
                $table->string('entry_frequency', 20)
                    ->default('once')
                    ->after('log_date');
            }

            if (! Schema::hasColumn('daily_wellbeing_logs', 'manual_entry')) {
                $table->boolean('manual_entry')
                    ->default(false)
                    ->after('entry_frequency');
            }
        });

        /*
         * Existing rows were historically a mixture of automatic daily source
         * snapshots and user-entered check-ins. Mark rows containing any manual
         * wellbeing reflection values as user check-ins; leave pure connected
         * source snapshots hidden from the manual check-in list.
         */
        DB::table('daily_wellbeing_logs')
            ->where(function ($query): void {
                $query->whereNotNull('mood')
                    ->orWhereNotNull('energy_level')
                    ->orWhereNotNull('stress_level')
                    ->orWhereNotNull('pain_level')
                    ->orWhereNotNull('wellbeing_score')
                    ->orWhereNotNull('symptoms')
                    ->orWhereNotNull('self_care_activity')
                    ->orWhereNotNull('notes');
            })
            ->update([
                'manual_entry' => true,
                'entry_frequency' => 'once',
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('daily_wellbeing_logs')) {
            return;
        }

        Schema::table('daily_wellbeing_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('daily_wellbeing_logs', 'manual_entry')) {
                $table->dropColumn('manual_entry');
            }

            if (Schema::hasColumn('daily_wellbeing_logs', 'entry_frequency')) {
                $table->dropColumn('entry_frequency');
            }
        });
    }
};
