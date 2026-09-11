<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SocialMediaPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class SocialMediaReportService
{
    public function __construct(
        private readonly SocialMediaAnalyticsService $analytics
    ) {
    }

    public function report(
        User $user,
        string $period = 'month',
        ?string $platform = null,
        ?string $status = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        [$start, $end] = $this->range($user, $period, $from, $to);

        if (! Schema::hasTable('social_media_posts')) {
            return $this->emptyReport($period, $start, $end);
        }

        $query = SocialMediaPost::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('scheduled_at', [$start, $end])
                    ->orWhereBetween('published_at', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end]);
            });

        if ($platform) {
            $query->whereJsonContains('platforms', $platform);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $posts = $query
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        $timezone = $user->timezone ?: 'Africa/Kampala';
        $now = Carbon::now($timezone);

        $summary = [
            'total_posts' => $posts->count(),
            'draft' => $posts->where('status', 'draft')->count(),
            'scheduled' => $posts->where('status', 'scheduled')->count(),
            'ready_to_post' => $posts->where('status', 'ready_to_share')->count(),
            'published' => $posts->where('status', 'published')->count(),
            'failed' => $posts->where('status', 'failed')->count(),
            'overdue' => $posts->filter(fn ($post) =>
                $post->scheduled_at &&
                in_array($post->status, ['scheduled', 'ready_to_share'], true) &&
                $post->scheduled_at->lt($now)
            )->count(),
        ];

        $platformBreakdown = [];
        foreach ([
            'instagram', 'facebook', 'x', 'tiktok', 'linkedin',
            'whatsapp_status', 'whatsapp_channel',
        ] as $key) {
            $platformBreakdown[$key] = $posts->filter(
                fn ($post) => in_array($key, (array) ($post->platforms ?? []), true)
            )->count();
        }

        if (Schema::hasTable('social_media_post_metrics')) {
            $posts->load('metrics');
        } else {
            foreach ($posts as $post) {
                $post->setRelation('metrics', collect());
            }
        }

        return [
            'period' => $period,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'summary' => $summary,
            'platform_breakdown' => $platformBreakdown,
            'analytics' => $this->analytics->overviewForPosts($posts),
            'posts' => $posts,
            'sync' => [
                'api_synced_rows' => 0,
                'last_synced_at' => null,
                'error_rows' => 0,
            ],
        ];
    }

    private function emptyReport(string $period, Carbon $start, Carbon $end): array
    {
        return [
            'period' => $period,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'summary' => [
                'total_posts' => 0,
                'draft' => 0,
                'scheduled' => 0,
                'ready_to_post' => 0,
                'published' => 0,
                'failed' => 0,
                'overdue' => 0,
            ],
            'platform_breakdown' => [],
            'analytics' => $this->analytics->overviewForPosts(collect()),
            'posts' => collect(),
            'sync' => [
                'api_synced_rows' => 0,
                'last_synced_at' => null,
                'error_rows' => 0,
            ],
        ];
    }

    private function range(
        User $user,
        string $period,
        ?string $from,
        ?string $to
    ): array {
        $timezone = $user->timezone ?: 'Africa/Kampala';
        $now = Carbon::now($timezone);

        if ($from && $to) {
            return [
                Carbon::parse($from, $timezone)->startOfDay(),
                Carbon::parse($to, $timezone)->endOfDay(),
            ];
        }

        return match ($period) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            'week' => [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            'year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
            ],
            'all' => [
                Carbon::create(2000, 1, 1, 0, 0, 0, $timezone),
                $now->copy()->endOfDay(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };
    }
}
