<?php

namespace App\Console\Commands;

use App\Models\Debt;
use Illuminate\Console\Command;

class UpdateDebtOverdueStatuses extends Command
{
    protected $signature = 'debts:update-overdue-status';
    protected $description = 'Automatically mark debts overdue after their due date passes';

    public function handle(): int
    {
        $marked = Debt::query()
            ->whereIn('status', ['outstanding', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);

        $restored = Debt::query()
            ->where('status', 'overdue')
            ->where(function ($query): void {
                $query->whereNull('due_date')
                    ->orWhereDate('due_date', '>=', today());
            })
            ->update(['status' => 'outstanding']);

        $this->info("Marked overdue: {$marked}; restored outstanding: {$restored}");
        return self::SUCCESS;
    }
}
