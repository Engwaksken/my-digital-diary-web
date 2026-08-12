<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The earlier organization-tier seed data used "Seats" in the plan NAME
 * itself and in the 50-seat tier's BADGE text — those are stored data,
 * not template text, so the earlier seat->member terminology sweep
 * (which only touched blade views and controller messages) couldn't
 * reach them. Fixes both here.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([5, 10, 15, 20, 25, 50] as $seats) {
            DB::table('subscription_plans')
                ->where('key', 'organization_' . $seats)
                ->update(['name' => 'Organization — ' . $seats . ' Members']);
        }

        DB::table('subscription_plans')
            ->where('badge', 'Best Per-Seat Rate')
            ->update(['badge' => 'Best Per-Member Rate']);
    }

    public function down(): void
    {
        foreach ([5, 10, 15, 20, 25, 50] as $seats) {
            DB::table('subscription_plans')
                ->where('key', 'organization_' . $seats)
                ->update(['name' => 'Organization — ' . $seats . ' Seats']);
        }

        DB::table('subscription_plans')
            ->where('badge', 'Best Per-Member Rate')
            ->update(['badge' => 'Best Per-Seat Rate']);
    }
};
