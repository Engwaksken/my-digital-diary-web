<?php

namespace App\Services;

use App\Models\SocialMediaMetricSnapshot;
use App\Models\SocialMediaPost;
use App\Models\SocialMediaPostMetric;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocialMediaAnalyticsService
{
    private const META_VERSION = 'v25.0';
    private const LINKEDIN_VERSION = '202607';

    /**
     * Save manually supplied analytics. Manual entry is retained as a fallback
     * for platforms/posts that cannot expose analytics through an authorised API.
     */
    public function saveManual(SocialMediaPost $post, string $platform, array $values): SocialMediaPostMetric
    {
        abort_unless(
            in_array($platform, (array) ($post->platforms ?? []), true),
            422,
            'This platform is not attached to the post.'
        );

        $normalised = $this->normalise($values);

        return DB::transaction(function () use ($post, $platform, $values, $normalised) {
            $metric = SocialMediaPostMetric::updateOrCreate(
                [
                    'social_media_post_id' => $post->id,
                    'platform' => $platform,
                ],
                [
                    'user_id' => $post->user_id,
                    'external_post_id' => $values['external_post_id'] ?? null,
                    ...$normalised,
                    'synced_at' => now(),
                    'raw_metrics' => array_merge(
                        is_array($values['raw_metrics'] ?? null) ? $values['raw_metrics'] : [],
                        [
                            'source' => 'manual',
                            'sync_status' => 'manual',
                        ]
                    ),
                ]
            );

            $this->snapshot($post, $platform, $normalised);

            return $metric;
        });
    }

    /**
     * Register provider post IDs immediately after automatic publishing.
     * Without this step the scheduled sync command has nothing to query.
     */
    public function registerPublishingResults(SocialMediaPost $post, array $publishingResults): void
    {
        $platformResults = $publishingResults['platforms'] ?? [];

        if (! is_array($platformResults)) {
            return;
        }

        foreach ($platformResults as $platform => $result) {
            if (! is_array($result) || ($result['published'] ?? false) !== true) {
                continue;
            }

            $externalId = trim((string) ($result['external_post_id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $metric = SocialMediaPostMetric::firstOrNew([
                'social_media_post_id' => $post->id,
                'platform' => (string) $platform,
            ]);

            $existingRaw = is_array($metric->raw_metrics) ? $metric->raw_metrics : [];
            $metric->user_id = $post->user_id;
            $metric->external_post_id = $externalId;
            $metric->raw_metrics = array_merge($existingRaw, [
                'provider' => $result['provider'] ?? $platform,
                'publisher_result' => $result,
            ]);

            if (! $metric->exists) {
                $metric->raw_metrics = array_merge($metric->raw_metrics, [
                    'source' => 'publisher',
                    'sync_status' => 'waiting',
                ]);
            }

            $metric->save();
        }
    }

    /**
     * Sync every eligible metric row for a single post.
     */
    public function syncPost(SocialMediaPost $post, ?string $onlyPlatform = null): array
    {
        $results = [];

        $metrics = SocialMediaPostMetric::query()
            ->where('social_media_post_id', $post->id)
            ->whereNotNull('external_post_id')
            ->when($onlyPlatform, fn ($q) => $q->where('platform', $onlyPlatform))
            ->get();

        foreach ($metrics as $metric) {
            $results[$metric->platform] = $this->syncMetric($post, $metric);
        }

        return $results;
    }

    /**
     * Sync all eligible posts belonging to one user.
     */
    public function syncForUser(User $user, int $limit = 100): array
    {
        $rows = SocialMediaPostMetric::query()
            ->with('post')
            ->where('user_id', $user->id)
            ->whereNotNull('external_post_id')
            ->whereIn('platform', ['facebook', 'instagram', 'x', 'tiktok', 'linkedin'])
            ->orderByRaw('synced_at IS NULL DESC')
            ->orderBy('synced_at')
            ->limit(max(1, min($limit, 250)))
            ->get();

        $summary = [
            'attempted' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
            'results' => [],
        ];

        foreach ($rows as $metric) {
            if (! $metric->post) {
                $summary['skipped']++;
                continue;
            }

            $summary['attempted']++;
            $result = $this->syncMetric($metric->post, $metric);
            $summary['results'][] = $result;

            if (($result['ok'] ?? false) === true) {
                $summary['synced']++;
            } elseif (($result['skipped'] ?? false) === true) {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * Scheduler entry point. Sync older/stale rows first and keep the batch small
     * enough to respect provider rate limits.
     */
    public function syncDueMetrics(int $limit = 250): array
    {
        $this->backfillPublishingResults();

        $rows = SocialMediaPostMetric::query()
            ->with('post')
            ->whereNotNull('external_post_id')
            ->whereIn('platform', ['facebook', 'instagram', 'x', 'tiktok', 'linkedin'])
            ->where(function ($q) {
                $q->whereNull('synced_at')
                    ->orWhere('synced_at', '<=', now()->subMinutes(55));
            })
            ->orderByRaw('synced_at IS NULL DESC')
            ->orderBy('synced_at')
            ->limit(max(1, min($limit, 500)))
            ->get();

        $summary = [
            'attempted' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $metric) {
            if (! $metric->post) {
                $summary['skipped']++;
                continue;
            }

            $summary['attempted']++;
            $result = $this->syncMetric($metric->post, $metric);

            if (($result['ok'] ?? false) === true) {
                $summary['synced']++;
            } elseif (($result['skipped'] ?? false) === true) {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function overviewForPosts($posts): array
    {
        if ($posts->isEmpty()) {
            return [
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
                'last_synced_at' => null,
                'api_synced_rows' => 0,
                'sync_error_rows' => 0,
            ];
        }

        $metrics = SocialMediaPostMetric::whereIn('social_media_post_id', $posts->pluck('id'))->get();
        $engagements = $metrics->sum('engagements');
        $reach = $metrics->sum('reach');
        $views = $metrics->sum('views');
        $impressions = $metrics->sum('impressions');
        $denominator = $reach ?: ($views ?: $impressions);

        $lastSynced = $metrics
            ->filter(fn ($m) => $m->synced_at)
            ->sortByDesc('synced_at')
            ->first()?->synced_at;

        return [
            'total_views' => $views,
            'total_reach' => $reach,
            'total_impressions' => $impressions,
            'total_likes' => $metrics->sum('likes'),
            'total_comments' => $metrics->sum('comments'),
            'total_shares' => $metrics->sum('shares'),
            'total_saves' => $metrics->sum('saves'),
            'total_clicks' => $metrics->sum('clicks'),
            'total_replies' => $metrics->sum('replies'),
            'total_engagements' => $engagements,
            'engagement_rate' => $denominator
                ? round($engagements / $denominator * 100, 2)
                : 0,
            'last_synced_at' => optional($lastSynced)->toIso8601String(),
            'api_synced_rows' => $metrics->filter(function ($m) {
                return data_get($m->raw_metrics, 'source') === 'api';
            })->count(),
            'sync_error_rows' => $metrics->filter(function ($m) {
                return data_get($m->raw_metrics, 'sync_status') === 'error';
            })->count(),
        ];
    }

    private function backfillPublishingResults(): void
    {
        SocialMediaPost::query()
            ->whereNotNull('publishing_results')
            ->whereIn('status', ['published', 'ready_to_share'])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->each(function (SocialMediaPost $post) {
                if (is_array($post->publishing_results)) {
                    $this->registerPublishingResults($post, $post->publishing_results);
                }
            });
    }

    private function syncMetric(SocialMediaPost $post, SocialMediaPostMetric $metric): array
    {
        $platform = (string) $metric->platform;

        if (in_array($platform, ['whatsapp_status', 'whatsapp_channel'], true)) {
            return [
                'ok' => false,
                'skipped' => true,
                'platform' => $platform,
                'post_id' => $post->id,
                'reason' => 'WhatsApp Status/Channels do not expose normal post analytics through this integration.',
            ];
        }

        $account = DB::table('social_media_accounts')
            ->where('user_id', $post->user_id)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            return $this->recordSyncError($metric, 'No active connected account was found.');
        }

        $token = $this->decryptToken($account->oauth_access_token ?? null);
        if (! $token) {
            return $this->recordSyncError($metric, 'The account access token is missing, expired or cannot be decrypted.');
        }

        try {
            $provider = match ($platform) {
                'facebook' => $this->fetchFacebook($metric, $token),
                'instagram' => $this->fetchInstagram($metric, $token),
                'x' => $this->fetchX($metric, $token),
                'tiktok' => $this->fetchTikTok($metric, $token),
                'linkedin' => $this->fetchLinkedIn($metric, $token),
                default => throw new \RuntimeException('Unsupported analytics provider.'),
            };

            if (($provider['external_post_id'] ?? null) && $provider['external_post_id'] !== $metric->external_post_id) {
                $metric->external_post_id = (string) $provider['external_post_id'];
            }

            $normalised = $this->normalise($provider);
            $metric->fill([
                ...$normalised,
                'synced_at' => now(),
                'raw_metrics' => [
                    'source' => 'api',
                    'sync_status' => 'ok',
                    'provider' => $platform,
                    'fetched_at' => now()->toIso8601String(),
                    'available_metrics' => array_values($provider['available_metrics'] ?? []),
                    'response' => $provider['raw_metrics'] ?? [],
                ],
            ])->save();

            $this->snapshot($post, $platform, $normalised);

            return [
                'ok' => true,
                'platform' => $platform,
                'post_id' => $post->id,
                'external_post_id' => $metric->external_post_id,
                'synced_at' => $metric->synced_at?->toIso8601String(),
                'metrics' => $normalised,
                'available_metrics' => $provider['available_metrics'] ?? [],
            ];
        } catch (Throwable $e) {
            Log::warning('Social media analytics sync failed.', [
                'post_id' => $post->id,
                'platform' => $platform,
                'metric_id' => $metric->id,
                'error' => $e->getMessage(),
            ]);

            return $this->recordSyncError($metric, $e->getMessage());
        }
    }

    private function fetchFacebook(SocialMediaPostMetric $metric, string $token): array
    {
        $postId = rawurlencode((string) $metric->external_post_id);

        $post = Http::timeout(30)
            ->get("https://graph.facebook.com/".self::META_VERSION."/{$postId}", [
                'fields' => 'shares,comments.limit(0).summary(true),reactions.limit(0).summary(true)',
                'access_token' => $token,
            ]);

        $this->throwForProvider($post, 'Facebook');

        $insights = Http::timeout(30)
            ->get("https://graph.facebook.com/".self::META_VERSION."/{$postId}/insights", [
                'metric' => 'post_impressions,post_impressions_unique,post_clicks,post_video_views',
                'access_token' => $token,
            ]);

        // Some Page/API versions do not expose every requested insight. Keep
        // public reaction/comment/share counts even if the insights request fails.
        $insightValues = $insights->successful()
            ? $this->metaInsightValues((array) $insights->json('data', []))
            : [];

        $likes = (int) data_get($post->json(), 'reactions.summary.total_count', 0);
        $comments = (int) data_get($post->json(), 'comments.summary.total_count', 0);
        $shares = (int) data_get($post->json(), 'shares.count', 0);

        return [
            'views' => (int) ($insightValues['post_video_views'] ?? 0),
            'reach' => (int) ($insightValues['post_impressions_unique'] ?? 0),
            'impressions' => (int) ($insightValues['post_impressions'] ?? 0),
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $shares,
            'saves' => 0,
            'clicks' => (int) ($insightValues['post_clicks'] ?? 0),
            'replies' => 0,
            'available_metrics' => array_values(array_unique(array_merge(
                ['likes', 'comments', 'shares'],
                array_keys($insightValues)
            ))),
            'raw_metrics' => [
                'post' => $post->json(),
                'insights' => $insights->successful() ? $insights->json() : [
                    'http_status' => $insights->status(),
                    'error' => $insights->json('error.message'),
                ],
            ],
        ];
    }

    private function fetchInstagram(SocialMediaPostMetric $metric, string $token): array
    {
        $mediaId = rawurlencode((string) $metric->external_post_id);

        $media = Http::timeout(30)
            ->get("https://graph.facebook.com/".self::META_VERSION."/{$mediaId}", [
                'fields' => 'like_count,comments_count',
                'access_token' => $token,
            ]);

        $this->throwForProvider($media, 'Instagram');

        $insights = Http::timeout(30)
            ->get("https://graph.facebook.com/".self::META_VERSION."/{$mediaId}/insights", [
                'metric' => 'views,reach,saved,shares,total_interactions',
                'access_token' => $token,
            ]);

        $insightValues = $insights->successful()
            ? $this->metaInsightValues((array) $insights->json('data', []))
            : [];

        return [
            'views' => (int) ($insightValues['views'] ?? 0),
            'reach' => (int) ($insightValues['reach'] ?? 0),
            'impressions' => 0,
            'likes' => (int) $media->json('like_count', 0),
            'comments' => (int) $media->json('comments_count', 0),
            'shares' => (int) ($insightValues['shares'] ?? 0),
            'saves' => (int) ($insightValues['saved'] ?? 0),
            'clicks' => 0,
            'replies' => 0,
            'available_metrics' => array_values(array_unique(array_merge(
                ['likes', 'comments'],
                array_keys($insightValues)
            ))),
            'raw_metrics' => [
                'media' => $media->json(),
                'insights' => $insights->successful() ? $insights->json() : [
                    'http_status' => $insights->status(),
                    'error' => $insights->json('error.message'),
                ],
            ],
        ];
    }

    private function fetchX(SocialMediaPostMetric $metric, string $token): array
    {
        $postId = rawurlencode((string) $metric->external_post_id);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get("https://api.x.com/2/tweets/{$postId}", [
                'tweet.fields' => 'public_metrics,attachments',
                'expansions' => 'attachments.media_keys',
                'media.fields' => 'public_metrics',
            ]);

        $this->throwForProvider($response, 'X');

        $public = (array) $response->json('data.public_metrics', []);
        $media = (array) $response->json('includes.media', []);
        $private = [];

        // Private click metrics are only returned for the authorised post owner
        // and may be unavailable depending on X plan/scope or post age.
        $privateResponse = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get("https://api.x.com/2/tweets/{$postId}", [
                'tweet.fields' => 'non_public_metrics',
            ]);

        if ($privateResponse->successful()) {
            $private = (array) $privateResponse->json('data.non_public_metrics', []);
        }

        $mediaViews = 0;
        foreach ($media as $item) {
            $mediaViews += (int) data_get($item, 'public_metrics.view_count', 0);
        }

        $shares = (int) ($public['retweet_count'] ?? 0)
            + (int) ($public['quote_count'] ?? 0);

        $available = ['impressions', 'likes', 'comments', 'shares', 'saves', 'replies'];
        if ($mediaViews > 0) {
            $available[] = 'views';
        }
        if (! empty($private)) {
            $available[] = 'clicks';
        }

        return [
            'views' => $mediaViews,
            'reach' => 0,
            'impressions' => (int) ($public['impression_count'] ?? $private['impression_count'] ?? 0),
            'likes' => (int) ($public['like_count'] ?? 0),
            'comments' => (int) ($public['reply_count'] ?? 0),
            'shares' => $shares,
            'saves' => (int) ($public['bookmark_count'] ?? 0),
            'clicks' => (int) ($private['url_link_clicks'] ?? 0)
                + (int) ($private['user_profile_clicks'] ?? 0),
            'replies' => (int) ($public['reply_count'] ?? 0),
            'available_metrics' => array_values(array_unique($available)),
            'raw_metrics' => [
                'public' => $response->json(),
                'private' => $privateResponse->successful()
                    ? $privateResponse->json()
                    : [
                        'http_status' => $privateResponse->status(),
                        'message' => $privateResponse->json('detail') ?? $privateResponse->json('title'),
                    ],
            ],
        ];
    }

    private function fetchTikTok(SocialMediaPostMetric $metric, string $token): array
    {
        $externalId = (string) $metric->external_post_id;
        $publishId = data_get($metric->raw_metrics, 'publisher_result.external_post_id');

        if (str_contains($externalId, '_pub_') || str_starts_with($externalId, 'v_pub') || str_starts_with($externalId, 'p_pub')) {
            $publishId = $externalId;
        }

        if ($publishId) {
            $status = Http::withToken($token)
                ->asJson()
                ->acceptJson()
                ->timeout(30)
                ->post('https://open.tiktokapis.com/v2/post/publish/status/fetch/', [
                    'publish_id' => $publishId,
                ]);

            $this->throwForProvider($status, 'TikTok publishing status');

            $ids = (array) $status->json('data.publicaly_available_post_id', []);
            if (! empty($ids)) {
                $externalId = (string) $ids[0];
            } elseif ((string) $status->json('data.status') !== 'PUBLISH_COMPLETE') {
                throw new \RuntimeException(
                    'TikTok has not returned a public post ID yet. Status: '.
                    ((string) $status->json('data.status', 'processing'))
                );
            }
        }

        if ($externalId === '' || str_contains($externalId, '_pub_')) {
            throw new \RuntimeException('TikTok public video ID is not available yet.');
        }

        $response = Http::withToken($token)
            ->asJson()
            ->acceptJson()
            ->timeout(30)
            ->post(
                'https://open.tiktokapis.com/v2/video/query/?fields=id,like_count,comment_count,share_count,view_count',
                [
                    'filters' => [
                        'video_ids' => [$externalId],
                    ],
                ]
            );

        $this->throwForProvider($response, 'TikTok');

        $video = (array) data_get($response->json(), 'data.videos.0', []);
        if (empty($video)) {
            throw new \RuntimeException('TikTok did not return analytics for this video.');
        }

        return [
            'external_post_id' => (string) ($video['id'] ?? $externalId),
            'views' => (int) ($video['view_count'] ?? 0),
            'reach' => 0,
            'impressions' => 0,
            'likes' => (int) ($video['like_count'] ?? 0),
            'comments' => (int) ($video['comment_count'] ?? 0),
            'shares' => (int) ($video['share_count'] ?? 0),
            'saves' => 0,
            'clicks' => 0,
            'replies' => 0,
            'available_metrics' => ['views', 'likes', 'comments', 'shares'],
            'raw_metrics' => $response->json(),
        ];
    }

    private function fetchLinkedIn(SocialMediaPostMetric $metric, string $token): array
    {
        $postUrn = (string) $metric->external_post_id;
        $encoded = rawurlencode($postUrn);

        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'LinkedIn-Version' => self::LINKEDIN_VERSION,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->timeout(30)
            ->get("https://api.linkedin.com/rest/socialActions/{$encoded}");

        $this->throwForProvider($response, 'LinkedIn');

        $json = $response->json();
        $likes = (int) (
            data_get($json, 'likesSummary.totalLikes')
            ?? data_get($json, 'likesSummary.aggregatedTotalLikes')
            ?? 0
        );
        $comments = (int) (
            data_get($json, 'commentsSummary.aggregatedTotalComments')
            ?? data_get($json, 'commentsSummary.totalFirstLevelComments')
            ?? 0
        );

        return [
            'views' => 0,
            'reach' => 0,
            'impressions' => 0,
            'likes' => $likes,
            'comments' => $comments,
            'shares' => 0,
            'saves' => 0,
            'clicks' => 0,
            'replies' => 0,
            'available_metrics' => ['likes', 'comments'],
            'raw_metrics' => $json,
        ];
    }

    private function metaInsightValues(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $value = data_get($row, 'values.0.value');
            if ($value === null) {
                $value = $row['value'] ?? null;
            }

            if (is_numeric($value)) {
                $values[$name] = (int) $value;
            }
        }

        return $values;
    }

    private function normalise(array $values): array
    {
        $normalised = [];
        foreach (['views', 'reach', 'impressions', 'likes', 'comments', 'shares', 'saves', 'clicks', 'replies'] as $key) {
            $normalised[$key] = max(0, (int) ($values[$key] ?? 0));
        }

        // Replies are a comment subtype on X. Avoid double counting them when
        // comments already contains reply_count.
        $normalised['engagements'] = $normalised['likes']
            + $normalised['comments']
            + $normalised['shares']
            + $normalised['saves']
            + $normalised['clicks'];

        $denominator = $normalised['reach']
            ?: ($normalised['views'] ?: $normalised['impressions']);

        $normalised['engagement_rate'] = $denominator
            ? round($normalised['engagements'] / $denominator * 100, 2)
            : 0;

        return $normalised;
    }

    private function snapshot(SocialMediaPost $post, string $platform, array $normalised): void
    {
        SocialMediaMetricSnapshot::create([
            'user_id' => $post->user_id,
            'social_media_post_id' => $post->id,
            'platform' => $platform,
            ...$normalised,
            'captured_at' => now(),
        ]);
    }

    private function recordSyncError(SocialMediaPostMetric $metric, string $message): array
    {
        $existing = is_array($metric->raw_metrics) ? $metric->raw_metrics : [];

        $metric->forceFill([
            'raw_metrics' => array_merge($existing, [
                'sync_status' => 'error',
                'sync_error' => $message,
                'sync_attempted_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return [
            'ok' => false,
            'platform' => $metric->platform,
            'post_id' => $metric->social_media_post_id,
            'error' => $message,
        ];
    }

    private function throwForProvider($response, string $provider): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.message')
            ?? $response->json('error.message')
            ?? $response->json('message')
            ?? $response->json('detail')
            ?? $response->body();

        throw new \RuntimeException(
            "{$provider} analytics request failed ({$response->status()}): ".mb_strimwidth((string) $message, 0, 500, '…')
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
}
