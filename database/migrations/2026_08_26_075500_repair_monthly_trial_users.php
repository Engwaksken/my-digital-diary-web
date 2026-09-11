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
            ! Schema::hasTable('users')
            || ! Schema::hasTable('subscription_plans')
            || ! Schema::hasColumn('users', 'subscription_plan_id')
        ) {
            return;
        }

        $monthlyPlanId = $this->monthlyPlanId();

        if (! $monthlyPlanId) {
            return;
        }

        $query = DB::table('users')
            ->where(function ($q): void {
                $q->whereNull('subscription_plan_id')
                    ->orWhere('subscription_plan_id', 0);
            });

        if (Schema::hasColumn('users', 'subscription_status')) {
            $query->where(function ($q): void {
                $q->whereNull('subscription_status')
                    ->orWhereRaw(
                        "LOWER(TRIM(subscription_status)) IN ('', 'trial', 'trialing')"
                    );
            });
        }

        /*
         * Organisation members inherit the owner's workspace plan.
         */
        if (Schema::hasColumn('users', 'organization_id')) {
            $query->whereNull('organization_id');
        }

        if (
            Schema::hasTable('organization_members')
            && Schema::hasColumn('organization_members', 'user_id')
        ) {
            $query->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('organization_members')
                    ->whereColumn(
                        'organization_members.user_id',
                        'users.id'
                    )
                    ->whereIn(
                        'organization_members.status',
                        ['active', 'invited']
                    );
            });
        }

        $ids = (clone $query)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $updates = [
            'subscription_plan_id' => $monthlyPlanId,
        ];

        if (Schema::hasColumn('users', 'subscription_status')) {
            $updates['subscription_status'] = $this->trialStatusValue();
        }

        if (Schema::hasColumn('users', 'subscription_started_at')) {
            $updates['subscription_started_at'] = now();
        }

        DB::table('users')
            ->whereIn('id', $ids)
            ->update($updates);

        if (Schema::hasColumn('users', 'trial_ends_at')) {
            DB::table('users')
                ->whereIn('id', $ids)
                ->whereNull('trial_ends_at')
                ->update([
                    'trial_ends_at' => now()->addDays(14),
                ]);
        }
    }

    public function down(): void
    {
        // Non-destructive production repair.
    }

    private function trialStatusValue(): string
    {
        if (! Schema::hasColumn('users', 'subscription_status')) {
            return 'trialing';
        }

        try {
            $column = DB::selectOne(
                "SHOW COLUMNS FROM `users` LIKE 'subscription_status'"
            );

            $type = strtolower((string) ($column->Type ?? ''));

            if (str_starts_with($type, 'enum(')) {
                preg_match_all("/'([^']+)'/", $type, $matches);
                $allowed = array_map('strtolower', $matches[1] ?? []);

                if (in_array('trial', $allowed, true)) {
                    return 'trial';
                }

                if (in_array('trialing', $allowed, true)) {
                    return 'trialing';
                }

                if (in_array('active', $allowed, true)) {
                    return 'active';
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return 'trialing';
    }

    private function monthlyPlanId(): ?int
    {
        foreach (['code', 'slug'] as $column) {
            if (! Schema::hasColumn('subscription_plans', $column)) {
                continue;
            }

            $id = DB::table('subscription_plans')
                ->whereRaw(
                    "LOWER(TRIM({$column})) = ?",
                    ['monthly']
                )
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        foreach (['name', 'title'] as $column) {
            if (! Schema::hasColumn('subscription_plans', $column)) {
                continue;
            }

            $id = DB::table('subscription_plans')
                ->whereRaw(
                    "LOWER({$column}) LIKE ?",
                    ['%monthly%']
                )
                ->orderBy('id')
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        if (Schema::hasColumn('subscription_plans', 'billing_interval')) {
            $id = DB::table('subscription_plans')
                ->whereRaw(
                    'LOWER(TRIM(billing_interval)) = ?',
                    ['monthly']
                )
                ->orderBy('id')
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        if (Schema::hasColumn('subscription_plans', 'duration_months')) {
            $query = DB::table('subscription_plans')
                ->where('duration_months', 1);

            if (Schema::hasColumn('subscription_plans', 'category')) {
                $query->where(function ($q): void {
                    $q->whereNull('category')
                        ->orWhereRaw(
                            'LOWER(TRIM(category)) IN (?, ?, ?)',
                            ['individual', 'personal', 'monthly']
                        );
                });
            }

            $id = $query->orderBy('id')->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }
};
