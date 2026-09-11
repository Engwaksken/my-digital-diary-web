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
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'current_step_goal')) {
            Schema::table('users', function (Blueprint $table): void {
                $column = $table->unsignedInteger('current_step_goal')->nullable();

                if (Schema::hasColumn('users', 'timezone')) {
                    $column->after('timezone');
                }
            });
        }

        /*
         * Preserve existing users' latest sensible target where possible.
         * New users remain NULL until first use, then start at 5,000.
         */
        if (Schema::hasTable('daily_steps')) {
            $latest = DB::table('daily_steps')
                ->select('user_id', DB::raw('MAX(tracking_date) AS latest_date'))
                ->groupBy('user_id')
                ->get();

            foreach ($latest as $row) {
                $goal = DB::table('daily_steps')
                    ->where('user_id', $row->user_id)
                    ->where('tracking_date', $row->latest_date)
                    ->value('daily_goal');

                if ($goal !== null) {
                    DB::table('users')
                        ->where('id', $row->user_id)
                        ->whereNull('current_step_goal')
                        ->update([
                            'current_step_goal' => max(5000, min(10000, (int) $goal)),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')
            && Schema::hasColumn('users', 'current_step_goal')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('current_step_goal');
            });
        }
    }
};
