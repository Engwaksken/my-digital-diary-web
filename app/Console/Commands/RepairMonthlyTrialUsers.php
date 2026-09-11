<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RepairMonthlyTrialUsers extends Command
{
    protected $signature = 'subscriptions:repair-monthly-trials
                            {--dry-run : Show how many users would be updated}';

    protected $description =
        'Assign the Monthly plan to independent trial users that have no plan.';

    public function handle(): int
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('subscription_plans')
            || ! Schema::hasColumn('users', 'subscription_plan_id')
        ) {
            $this->error('Required users/subscription plan schema is missing.');
            return self::FAILURE;
        }

        $planId = $this->findMonthlyPlanId();

        if (! $planId) {
            $this->error(
                'No Monthly/one-month subscription plan could be found.'
            );
            return self::FAILURE;
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
         * Do not make owner-managed organisation members independently billed.
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

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info(
                "{$count} independent user(s) would be assigned Monthly · Trial."
            );
            return self::SUCCESS;
        }

        $updates = [
            'subscription_plan_id' => $planId,
        ];

        if (Schema::hasColumn('users', 'subscription_status')) {
            $updates['subscription_status'] = $this->trialStatusValue();
        }

        if (Schema::hasColumn('users', 'subscription_started_at')) {
            $updates['subscription_started_at'] = now();
        }

        if (Schema::hasColumn('users', 'trial_ends_at')) {
            /*
             * The query builder update cannot conditionally keep existing
             * values per row, so fill only missing trial dates separately.
             */
            DB::table('users')
                ->whereIn('id', (clone $query)->pluck('id'))
                ->whereNull('trial_ends_at')
                ->update(['trial_ends_at' => now()->addDays(14)]);
        }

        $updated = $query->update($updates);

        $this->info(
            "{$updated} user(s) repaired to Monthly · Trial."
        );

        return self::SUCCESS;
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

    private function findMonthlyPlanId(): ?int
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
}
