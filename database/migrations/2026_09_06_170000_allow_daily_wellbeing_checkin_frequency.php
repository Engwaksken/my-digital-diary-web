<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('daily_wellbeing_logs')
            || ! Schema::hasColumn(
                'daily_wellbeing_logs',
                'entry_frequency'
            )
        ) {
            return;
        }

        /*
         * entry_frequency is a VARCHAR column, so Daily requires no ENUM
         * alteration. Keep legacy/null values valid and predictable.
         */
        DB::table('daily_wellbeing_logs')
            ->whereNull('entry_frequency')
            ->update(['entry_frequency' => 'once']);

        DB::table('daily_wellbeing_logs')
            ->whereNotIn('entry_frequency', [
                'once',
                'daily',
                'weekly',
                'monthly',
            ])
            ->update(['entry_frequency' => 'once']);
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('daily_wellbeing_logs')
            || ! Schema::hasColumn(
                'daily_wellbeing_logs',
                'entry_frequency'
            )
        ) {
            return;
        }

        DB::table('daily_wellbeing_logs')
            ->where('entry_frequency', 'daily')
            ->update(['entry_frequency' => 'once']);
    }
};
