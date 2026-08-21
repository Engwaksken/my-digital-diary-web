<?php

namespace App\Services;

use App\Models\SocialMediaPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SocialMediaReportService
{
    public function __construct(private readonly SocialMediaAnalyticsService $analytics) {}
    public function report(User $user, string $period = 'month', ?string $platform = null, ?string $status = null, ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->range($user, $period, $from, $to);

        $query = SocialMediaPost::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('scheduled_at', [$start, $end])
                  ->orWhereBetween('published_at', [$start, $end])
                  ->orWhereBetween('created_at', [$start, $end]);
            });

        if ($platform) $query->whereJsonContains('platforms', $platform);
        if ($status) $query->where('status', $status);

        $posts = $query->orderByDesc('scheduled_at')->orderByDesc('id')->get();
        $now = Carbon::now($user->timezone ?: 'Africa/Kampala');

        $summary = [
            'total_posts' => $posts->count(),
            'draft' => $posts->where('status', 'draft')->count(),
            'scheduled' => $posts->where('status', 'scheduled')->count(),
            'ready_to_post' => $posts->where('status', 'ready_to_share')->count(),
            'published' => $posts->where('status', 'published')->count(),
            'failed' => $posts->where('status', 'failed')->count(),
            'overdue' => $posts->filter(fn ($post) =>
                $post->scheduled_at &&
                in_array($post->status, ['scheduled','ready_to_share'], true) &&
                $post->scheduled_at->lt($now)
            )->count(),
        ];

        $platformBreakdown = [];
        foreach (['instagram','facebook','x','tiktok','linkedin','whatsapp_status','whatsapp_channel'] as $key) {
            $platformBreakdown[$key] = $posts->filter(
                fn ($post) => in_array($key, (array) ($post->platforms ?? []), true)
            )->count();
        }

        $loadedPosts = $posts->load('metrics');
        $metricRows = $loadedPosts->flatMap(fn ($post) => $post->metrics);
        $lastSynced = $metricRows->filter(fn ($metric) => $metric->synced_at)
            ->sortByDesc('synced_at')
            ->first()?->synced_at;

        $sync = [
            'trackable_rows' => $metricRows->whereNotNull('external_post_id')->count(),
            'api_synced_rows' => $metricRows->filter(fn ($metric) => data_get($metric->raw_metrics, 'source') === 'api')->count(),
            'error_rows' => $metricRows->filter(fn ($metric) => data_get($metric->raw_metrics, 'sync_status') === 'error')->count(),
            'last_synced_at' => optional($lastSynced)->toIso8601String(),
        ];

        return [
            'period' => $period,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'summary' => $summary,
            'platform_breakdown' => $platformBreakdown,
            'analytics' => $this->analytics->overviewForPosts($posts),
            'sync' => $sync,
            'posts' => $loadedPosts,
        ];
    }

    private function range(User $user, string $period, ?string $from, ?string $to): array
    {
        $tz = $user->timezone ?: 'Africa/Kampala';
        $now = Carbon::now($tz);

        if ($from && $to) {
            return [Carbon::parse($from, $tz)->startOfDay(), Carbon::parse($to, $tz)->endOfDay()];
        }

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'all' => [Carbon::create(2000,1,1,0,0,0,$tz), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }
}
