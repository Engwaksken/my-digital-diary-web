<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('subscription_plans')
            || ! Schema::hasColumn('users', 'subscription_plan_id')
            || ! Schema::hasColumn('users', 'subscription_status')
        ) {
            return;
        }

        $monthlyPlanId = null;

        foreach (['code', 'slug', 'name', 'title'] as $column) {
            if (! Schema::hasColumn('subscription_plans', $column)) {
                continue;
            }

            $monthlyPlanId = DB::table('subscription_plans')
                ->whereRaw(
                    'LOWER(TRIM('.$column.')) = ?',
                    ['monthly']
                )
                ->value('id');

            if ($monthlyPlanId) {
                break;
            }
        }

        if (! $monthlyPlanId) {
            return;
        }

        DB::table('users')
            ->whereIn(
                DB::raw(
                    "LOWER(TRIM(COALESCE(subscription_status, '')))"
                ),
                ['trial', 'trialing']
            )
            ->where(function ($query): void {
                $query->whereNull('subscription_plan_id')
                    ->orWhere(
                        'subscription_plan_id',
                        0
                    );
            })
            ->update([
                'subscription_plan_id' => $monthlyPlanId,
            ]);
    }

    public function down(): void
    {
        /*
         * Non-destructive rollback: we cannot reliably distinguish a Monthly
         * plan assigned by this migration from one deliberately selected.
         */
    }
};
