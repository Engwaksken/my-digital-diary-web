<?php

namespace App\Console\Commands;

use App\Models\SpiritualPractice;
use App\Services\RecurringSpiritualPracticeService;
use Illuminate\Console\Command;

class GenerateRecurringSpiritualPractices extends Command
{
    protected $signature = 'spiritual-growth:generate-recurring {--limit=500}';
    protected $description = 'Generate upcoming daily, weekly and monthly Spiritual Growth sessions.';

    public function handle(RecurringSpiritualPracticeService $service): int
    {
        $generated = 0;
        $parents = SpiritualPractice::query()
            ->whereNull('recurrence_parent_id')
            ->whereNotNull('recurrence_frequency')
            ->where(function ($query) {
                $query->whereNull('recurrence_ends_at')
                    ->orWhereDate('recurrence_ends_at', '>=', now()->toDateString());
            })
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($parents as $parent) {
            $generated += $service->generateUpcoming($parent);
        }

        $this->info("Recurring Spiritual Growth generation complete. Created: {$generated}.");

        return self::SUCCESS;
    }
}
