<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SocialMediaMetricSnapshot;
use App\Models\SocialMediaPost;
use App\Models\SocialMediaPostMetric;
use App\Models\SocialMediaProviderConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class SocialMediaAnalyticsService
{
    private const META_VERSION = 'v25.0';
    private const LINKEDIN_VERSION = '202607';

    public function registerPublishingResults(SocialMediaPost $post, array $results): void
    {
        if (! Schema::hasTable('social_media_post_metrics')) {
            return;
        }

        foreach ((array) ($post->platforms ?? []) as $rawPlatform) {
            $platform = $this->normalisePlatform((string) $rawPlatform);
            $platformResult = $this->platformResult($results, $platform);

            if (! is_array($platformResult)) {
                continue;
            }

            if (
                ($platformResult['published'] ?? false) !== true
                && ($platformResult['submitted'] ?? false) !== true
            ) {
                continue;
            }

            $externalId = $this->extractExternalPostId($platformResult);

            if (! $externalId) {
                continue;
            }

            try {
                $metric = SocialMediaPostMetric::query()->firstOrNew([
                    'social_media_post_id' => $post->id,
                    'platform' => $platform,
                ]);

                $metric->user_id = $post->user_id;
                $metric->external_post_id = $externalId;

                if (! $metric->exists) {
                    foreach ($this->metricKeys() as $key) {
                        $metric->{$key} = 0;
                    }
                    $metric->engagement_rate = 0;
                }

                if (Schema::hasColumn('social_media_post_metrics', 'raw_metrics')) {
                    $metric->raw_metrics = ['publishing_result' => $platformResult];
                }

                $metric->save();
            } catch (Throwable $e) {
                Log::warning('Could not register social analytics publishing result.', [
                    'post_id' => $post->id,
                    'platform' => $platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function syncDueMetrics(int $limit = 250): array
    {
        $result = [
            'attempted' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        if (! Schema::hasTable('social_media_post_metrics')) {
            return $result;
        }

        $limit = max(1, min($limit, 500));

        $query = SocialMediaPostMetric::query()
            ->whereNotNull('external_post_id')
            ->where('external_post_id', '!=', '');

        if (Schema::hasColumn('social_media_post_metrics', 'synced_at')) {
            $query->where(function ($q): void {
                $q->whereNull('synced_at')
                    ->orWhere('synced_at', '<=', now()->subMinutes(30));
            });
        }

        $metrics = $query->orderBy('id')->limit($limit)->get();

        foreach ($metrics as $metric) {
            $result['attempted']++;

            try {
                $post = SocialMediaPost::query()->find($metric->social_media_post_id);

                if (! $post) {
                    $result['skipped']++;
                    continue;
                }

                $platform = $this->normalisePlatform((string) $metric->platform);

                if (! $this->supportsAutomaticMetrics($platform)) {
                    $result['skipped']++;
                    continue;
                }

                $account = DB::table('social_media_accounts')
                    ->where('user_id', $post->user_id)
                    ->where('platform', $platform)
                    ->where('is_active', true)
                    ->first();

                if (! $account) {
                    $result['skipped']++;
                    continue;
                }

                if (! SocialMediaProviderConfig::enabledFor($platform)) {
                    $result['skipped']++;
                    continue;
                }

                $token = $this->decryptToken($account->oauth_access_token ?? null);

                if (! $token) {
                    $result['skipped']++;
                    continue;
                }

                $externalId = trim((string) $metric->external_post_id);

                $values = match ($platform) {
                    'x' => $this->fetchXMetrics($externalId, $token),
                    'facebook' => $this->fetchFacebookMetrics($externalId, $token),
                    'instagram' => $this->fetchInstagramMetrics($externalId, $token),
                    'linkedin' => $this->fetchLinkedInMetrics($externalId, $token),
                    'tiktok' => $this->fetchTikTokMetrics($externalId, $token),
                    default => null,
                };

                if ($values === null) {
                    $result['skipped']++;
                    continue;
                }

                $this->saveAutomatic($post, $metric, $platform, $values);
                $result['synced']++;
            } catch (Throwable $e) {
                $result['failed']++;

                Log::warning('Automatic social analytics sync failed.', [
                    'metric_id' => $metric->id,
                    'post_id' => $metric->social_media_post_id ?? null,
                    'platform' => $metric->platform ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    public function saveManual(
        SocialMediaPost $post,
        string $platform,
        array $values
    ): SocialMediaPostMetric {
        $platform = $this->normalisePlatform($platform);

        $attachedPlatforms = collect((array) ($post->platforms ?? []))
            ->map(fn ($value): string => $this->normalisePlatform((string) $value))
            ->all();

        abort_unless(
            in_array($platform, $attachedPlatforms, true),
            422,
            'This platform is not attached to the post.'
        );

        if (! Schema::hasTable('social_media_post_metrics')) {
            throw new RuntimeException(
                'Social media analytics storage is not ready. Run the latest migrations.'
            );
        }

        return DB::transaction(function () use ($post, $platform, $values): SocialMediaPostMetric {
            $numbers = $this->normaliseNumbers($values);

            $metric = SocialMediaPostMetric::updateOrCreate(
                [
                    'social_media_post_id' => $post->id,
                    'platform' => $platform,
                ],
                [
                    'user_id' => $post->user_id,
                    'external_post_id' => $values['external_post_id'] ?? null,
                    ...$numbers,
                    'synced_at' => now(),
                    'raw_metrics' => $values['raw_metrics'] ?? null,
                ]
            );

            $this->createSnapshot($post, $platform, $numbers);

            return $metric;
        });
    }

    public function overviewForPosts(Collection $posts): array
    {
        $zero = [
            'total_views' => 0,
            'total_reach' => 0,
            'total_impressions' => 0,
            'total_likes' => 0,
            'total_comments' => 0,
            'total_shares' => 0,
            'total_saves' => 0,
            'total_clicks' => 0,
            'total_replies' => 0,
            'total_engagements' => 0,
            'engagement_rate' => 0,
        ];

        if ($posts->isEmpty() || ! Schema::hasTable('social_media_post_metrics')) {
            return $zero;
        }

        $metrics = SocialMediaPostMetric::query()
            ->whereIn('social_media_post_id', $posts->pluck('id'))
            ->get();

        $engagements = (int) $metrics->sum('engagements');
        $reach = (int) $metrics->sum('reach');
        $views = (int) $metrics->sum('views');
        $impressions = (int) $metrics->sum('impressions');
        $denominator = $reach ?: ($views ?: $impressions);

        return [
            'total_views' => $views,
            'total_reach' => $reach,
            'total_impressions' => $impressions,
            'total_likes' => (int) $metrics->sum('likes'),
            'total_comments' => (int) $metrics->sum('comments'),
            'total_shares' => (int) $metrics->sum('shares'),
            'total_saves' => (int) $metrics->sum('saves'),
            'total_clicks' => (int) $metrics->sum('clicks'),
            'total_replies' => (int) $metrics->sum('replies'),
            'total_engagements' => $engagements,
            'engagement_rate' => $denominator > 0
                ? round($engagements / $denominator * 100, 2)
                : 0,
        ];
    }

    private function saveAutomatic(
        SocialMediaPost $post,
        SocialMediaPostMetric $metric,
        string $platform,
        array $values
    ): void {
        DB::transaction(function () use ($post, $metric, $platform, $values): void {
            $numbers = $this->normaliseNumbers($values);

            $metric->forceFill([
                ...$numbers,
                'synced_at' => now(),
                'raw_metrics' => $values['raw_metrics'] ?? $values,
            ])->save();

            $this->createSnapshot($post, $platform, $numbers);
        });
    }

    private function createSnapshot(
        SocialMediaPost $post,
        string $platform,
        array $numbers
    ): void {
        if (! Schema::hasTable('social_media_metric_snapshots')) {
            return;
        }

        SocialMediaMetricSnapshot::create([
            'user_id' => $post->user_id,
            'social_media_post_id' => $post->id,
            'platform' => $platform,
            ...$numbers,
            'captured_at' => now(),
        ]);
    }

    private function normaliseNumbers(array $values): array
    {
        $numbers = [];

        foreach ($this->metricKeys() as $key) {
            $numbers[$key] = max(0, (int) ($values[$key] ?? 0));
        }

        $numbers['engagements'] =
            $numbers['likes']
            + $numbers['comments']
            + $numbers['shares']
            + $numbers['saves']
            + $numbers['clicks']
            + $numbers['replies'];

        $denominator = $numbers['reach'] ?: ($numbers['views'] ?: $numbers['impressions']);

        $numbers['engagement_rate'] = $denominator > 0
            ? round($numbers['engagements'] / $denominator * 100, 2)
            : 0;

        return $numbers;
    }

    private function metricKeys(): array
    {
        return [
            'views','reach','impressions','likes','comments',
            'shares','saves','clicks','replies',
        ];
    }

    private function fetchXMetrics(string $postId, string $token): array
    {
        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(30)
            ->get(
                'https://api.x.com/2/tweets/'.rawurlencode($postId),
                ['tweet.fields' => 'public_metrics']
            );

        $response->throw();

        $metrics = (array) $response->json('data.public_metrics', []);

        return [
            'views' => (int) ($metrics['impression_count'] ?? 0),
            'impressions' => (int) ($metrics['impression_count'] ?? 0),
            'likes' => (int) ($metrics['like_count'] ?? 0),
            'comments' => (int) ($metrics['reply_count'] ?? 0),
            'replies' => (int) ($metrics['reply_count'] ?? 0),
            'shares' => (int) (
                ($metrics['retweet_count'] ?? 0)
                + ($metrics['quote_count'] ?? 0)
            ),
            'saves' => (int) ($metrics['bookmark_count'] ?? 0),
            'raw_metrics' => $response->json(),
        ];
    }

    private function fetchFacebookMetrics(string $postId, string $token): array
    {
        $response = Http::acceptJson()
            ->timeout(30)
            ->get(
                'https://graph.facebook.com/'.self::META_VERSION.'/'.rawurlencode($postId),
                [
                    'fields' => 'likes.limit(0).summary(true),comments.limit(0).summary(true),shares',
                    'access_token' => $token,
                ]
            );

        $response->throw();

        return [
            'likes' => (int) $response->json('likes.summary.total_count', 0),
            'comments' => (int) $response->json('comments.summary.total_count', 0),
            'shares' => (int) $response->json('shares.count', 0),
            'raw_metrics' => $response->json(),
        ];
    }

    private function fetchInstagramMetrics(string $mediaId, string $token): array
    {
        $basic = Http::acceptJson()
            ->timeout(30)
            ->get(
                'https://graph.facebook.com/'.self::META_VERSION.'/'.rawurlencode($mediaId),
                [
                    'fields' => 'like_count,comments_count',
                    'access_token' => $token,
                ]
            );

        $basic->throw();

        $values = [
            'likes' => (int) $basic->json('like_count', 0),
            'comments' => (int) $basic->json('comments_count', 0),
        ];

        try {
            $insights = Http::acceptJson()
                ->timeout(30)
                ->get(
                    'https://graph.facebook.com/'.self::META_VERSION.'/'.rawurlencode($mediaId).'/insights',
                    [
                        'metric' => 'reach,impressions,saved,shares,views',
                        'access_token' => $token,
                    ]
                );

            if ($insights->successful()) {
                foreach ((array) $insights->json('data', []) as $metric) {
                    if (! is_array($metric)) {
                        continue;
                    }

                    $name = (string) ($metric['name'] ?? '');
                    $value = $metric['values'][0]['value']
                        ?? $metric['value']
                        ?? 0;

                    match ($name) {
                        'reach' => $values['reach'] = (int) $value,
                        'impressions' => $values['impressions'] = (int) $value,
                        'saved' => $values['saves'] = (int) $value,
                        'shares' => $values['shares'] = (int) $value,
                        'views' => $values['views'] = (int) $value,
                        default => null,
                    };
                }
            }
        } catch (Throwable $e) {
            Log::notice('Instagram insights unavailable; basic counts retained.', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
        }

        $values['raw_metrics'] = $basic->json();

        return $values;
    }

    private function fetchLinkedInMetrics(string $postUrn, string $token): array
    {
        $response = Http::acceptJson()
            ->withToken($token)
            ->withHeaders([
                'LinkedIn-Version' => self::LINKEDIN_VERSION,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->timeout(30)
            ->get(
                'https://api.linkedin.com/rest/socialActions/'.rawurlencode($postUrn)
            );

        $response->throw();

        return [
            'likes' => (int) $response->json('likesSummary.totalLikes', 0),
            'comments' => (int) $response->json(
                'commentsSummary.totalFirstLevelComments',
                0
            ),
            'raw_metrics' => $response->json(),
        ];
    }

    private function fetchTikTokMetrics(string $postId, string $token): array
    {
        $response = Http::asJson()
            ->acceptJson()
            ->withToken($token)
            ->timeout(30)
            ->post(
                'https://open.tiktokapis.com/v2/video/query/?fields=id,view_count,like_count,comment_count,share_count',
                [
                    'filters' => [
                        'video_ids' => [$postId],
                    ],
                ]
            );

        $response->throw();

        $video = (array) $response->json('data.videos.0', []);

        return [
            'views' => (int) ($video['view_count'] ?? 0),
            'likes' => (int) ($video['like_count'] ?? 0),
            'comments' => (int) ($video['comment_count'] ?? 0),
            'shares' => (int) ($video['share_count'] ?? 0),
            'raw_metrics' => $response->json(),
        ];
    }

    private function supportsAutomaticMetrics(string $platform): bool
    {
        return in_array(
            $platform,
            ['x','facebook','instagram','linkedin','tiktok'],
            true
        );
    }

    private function decryptToken(?string $token): ?string
    {
        $token = trim((string) $token);

        if ($token === '') {
            return null;
        }

        try {
            return Crypt::decryptString($token);
        } catch (Throwable) {
            return null;
        }
    }

    private function platformResult(array $results, string $platform): mixed
    {
        $aliases = $this->platformAliases($platform);

        foreach (['platforms','results','providers','publishing_results'] as $container) {
            $group = $results[$container] ?? null;

            if (! is_array($group)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $group)) {
                    return $group[$alias];
                }
            }
        }

        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $results)) {
                return $results[$alias];
            }
        }

        return null;
    }

    private function extractExternalPostId(mixed $result): ?string
    {
        if (! is_array($result)) {
            return null;
        }

        foreach (
            [
                'external_post_id','provider_post_id','post_id','media_id',
                'tweet_id','video_id','publish_id','id',
            ] as $key
        ) {
            $value = $result[$key] ?? null;

            if (is_string($value) || is_int($value)) {
                $value = trim((string) $value);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        foreach (['response','data','result','post','media'] as $container) {
            if (isset($result[$container]) && is_array($result[$container])) {
                $nested = $this->extractExternalPostId($result[$container]);

                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function normalisePlatform(string $platform): string
    {
        $platform = strtolower(trim($platform));

        return match ($platform) {
            'twitter','x-twitter','x_twitter' => 'x',
            'fb' => 'facebook',
            'ig' => 'instagram',
            'linkedin-page','linkedin_page' => 'linkedin',
            default => $platform,
        };
    }

    private function platformAliases(string $platform): array
    {
        return match ($platform) {
            'x' => ['x','twitter','x-twitter','x_twitter'],
            'facebook' => ['facebook','fb'],
            'instagram' => ['instagram','ig'],
            'linkedin' => ['linkedin','linkedin-page','linkedin_page'],
            default => [$platform],
        };
    }
}
