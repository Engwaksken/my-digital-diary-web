<?php

namespace App\Console\Commands;

use App\Services\SocialMediaAnalyticsService;
use Illuminate\Console\Command;

class SyncSocialMediaMetrics extends Command
{
    protected $signature = 'social-media:sync-metrics {--limit=250 : Maximum metric rows to sync in this run}';

    protected $description = 'Automatically fetch analytics for authorised social media posts';

    public function handle(SocialMediaAnalyticsService $analytics): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $result = $analytics->syncDueMetrics($limit);

        $this->info(sprintf(
            'Social analytics sync complete. Attempted: %d, synced: %d, failed: %d, skipped: %d.',
            $result['attempted'] ?? 0,
            $result['synced'] ?? 0,
            $result['failed'] ?? 0,
            $result['skipped'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
