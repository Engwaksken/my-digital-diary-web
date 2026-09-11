<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ShowSubscriptionStatusValues extends Command
{
    protected $signature = 'subscriptions:status-schema';

    protected $description =
        'Show the allowed MySQL values for users.subscription_status.';

    public function handle(): int
    {
        $column = DB::selectOne(
            "SHOW COLUMNS FROM `users` LIKE 'subscription_status'"
        );

        if (! $column) {
            $this->error('users.subscription_status was not found.');
            return self::FAILURE;
        }

        $this->line('Type: '.($column->Type ?? 'unknown'));
        $this->line('Default: '.($column->Default ?? 'NULL'));

        return self::SUCCESS;
    }
}
