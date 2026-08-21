<?php

namespace App\Services;

use App\Models\SocialMediaPost;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SocialMediaPublisherService
{
    private const META_VERSION = 'v25.0';
    private const LINKEDIN_VERSION = '202607';

    public function publish(SocialMediaPost $post): array
    {
        $results = [];
        $successes = 0;
        $failures = 0;

        foreach ((array) ($post->platforms ?? []) as $platform) {
            $platform = (string) $platform;
            $result = $this->publishPlatform($post, $platform);
            $results[$platform] = $result;

            if (($result['published'] ?? false) === true) {
                $successes++;
            } else {
                $failures++;
            }
        }

        return [
            'published' => $successes > 0 && $failures === 0,
            'partial' => $successes > 0 && $failures > 0,
            'published_count' => $successes,
            'failed_count' => $failures,
            'platforms' => $results,
            'processed_at' => now()->toIso8601String(),
        ];
    }

    private function publishPlatform(
        SocialMediaPost $post,
        string $platform
    ): array {
        $account = DB::table('social_media_accounts')
            ->where('user_id', $post->user_id)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->first();

        /*
         * WhatsApp's official Business Platform is a business-messaging API.
         * My Digital Diary therefore does not pretend that a normal Cloud API
         * token can publish Status/Channel content. Automatic Status/Channel
         * publishing is enabled only when the user has connected an external
         * automation provider/webhook that explicitly supports that target.
         *
         * Manual posting remains available regardless of provider setup.
         */
        if (in_array($platform, ['whatsapp_status', 'whatsapp_channel'], true)) {
            return $this->publishWhatsAppAutomation($post, $platform, $account);
        }

        if (! $account) {
            return $this->fallback(
                'No active account is connected for this platform.'
            );
        }

        $account = $account;

        if (! (bool) ($account->auto_publish_enabled ?? false)) {
            return $this->fallback(
                'Automatic publishing is disabled for this account.'
            );
        }

        $token = $this->decryptToken(
            $account->oauth_access_token ?? null
        );

        if (! $token) {
            return $this->fallback(
                'OAuth/API authorisation is missing or invalid.'
            );
        }

        return match ($platform) {
            'x' => $this->publishX($post, $token),
            'facebook' => $this->publishFacebook(
                $post,
                $token,
                (string) ($account->external_account_id ?? '')
            ),
            'instagram' => $this->publishInstagram(
                $post,
                $token,
                (string) ($account->external_account_id ?? '')
            ),
            'linkedin' => $this->publishLinkedIn(
                $post,
                $token,
                (string) ($account->external_account_id ?? '')
            ),
            'tiktok' => $this->publishTikTok($post, $token),
            default => $this->fallback('Unsupported platform.'),
        };
    }

    private function publishWhatsAppAutomation(
        SocialMediaPost $post,
        string $platform,
        ?object $account
    ): array {
        if (! $account) {
            return $this->fallback(
                'No WhatsApp automation account is connected. Use Post now for manual sharing, or connect an automation provider.'
            );
        }

        if (! (bool) ($account->auto_publish_enabled ?? false)) {
            return $this->fallback(
                'WhatsApp automatic posting is disabled. The post is still available for manual sharing.'
            );
        }

        $endpoint = trim((string) ($account->automation_endpoint ?? ''));
        if ($endpoint === '') {
            return $this->fallback(
                'WhatsApp automatic posting needs a provider webhook/API endpoint. Manual sharing is still available.'
            );
        }

        $provider = trim((string) ($account->automation_provider ?? ''));
        $provider = $provider !== '' ? $provider : 'custom_webhook';

        $secret = $this->decryptToken($account->automation_secret ?? null);
        $token = $this->decryptToken($account->oauth_access_token ?? null);

        $payload = [
            'event' => 'social_media.publish',
            'platform' => $platform,
            'target' => $platform === 'whatsapp_channel' ? 'channel' : 'status',
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'caption' => $post->caption,
                'hashtags' => $post->hashtags,
                'text' => $post->shareText(),
                'media_type' => $post->media_type,
                'media_url' => $this->mediaUrl($post),
                'link_url' => method_exists($post, 'attachedLink') ? $post->attachedLink() : null,
                'scheduled_at' => optional($post->scheduled_at)->toIso8601String(),
            ],
            'account' => [
                'external_account_id' => $account->external_account_id ?? null,
                'account_name' => $account->account_name ?? null,
                'username' => $account->username ?? null,
            ],
        ];

        try {
            $request = Http::asJson()
                ->acceptJson()
                ->timeout(45)
                ->withHeaders([
                    'X-My-Digital-Diary-Event' => 'social_media.publish',
                    'X-My-Digital-Diary-Platform' => $platform,
                ]);

            if ($secret) {
                $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                $request = $request->withHeaders([
                    'X-My-Digital-Diary-Signature' => hash_hmac('sha256', $body, $secret),
                ]);
            }

            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->post($endpoint, $payload);

            if (! $response->successful()) {
                return [
                    'published' => false,
                    'mode' => 'automatic',
                    'provider' => $provider,
                    'error' => 'WhatsApp automation provider returned HTTP '.$response->status().'.',
                    'response' => $response->json(),
                ];
            }

            $published = $response->json('published');
            if ($published === false) {
                return [
                    'published' => false,
                    'mode' => 'automatic',
                    'provider' => $provider,
                    'error' => (string) ($response->json('error') ?: 'WhatsApp automation provider did not confirm publishing.'),
                    'response' => $response->json(),
                ];
            }

            return [
                'published' => true,
                'mode' => 'automatic',
                'provider' => $provider,
                'external_post_id' => (string) (
                    $response->json('external_post_id')
                    ?: $response->json('id', '')
                ),
                'response' => $response->json(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'published' => false,
                'mode' => 'automatic',
                'provider' => $provider,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function publishX(
        SocialMediaPost $post,
        string $token
    ): array {
        $text = trim($post->shareText());

        if ($text === '') {
            return $this->fallback('The X post has no text.');
        }

        return $this->request(
            'x',
            fn () => Http::asJson()
                ->acceptJson()
                ->withToken($token)
                ->timeout(30)
                ->post('https://api.x.com/2/tweets', [
                    'text' => $text,
                ]),
            fn ($response) => [
                'external_post_id' =>
                    (string) $response->json('data.id', ''),
            ]
        );
    }

    private function publishFacebook(
        SocialMediaPost $post,
        string $token,
        string $pageId
    ): array {
        if ($pageId === '') {
            return $this->fallback(
                'Facebook Page ID is missing.'
            );
        }

        $caption = trim($post->shareText());
        $mediaUrl = $this->mediaUrl($post);

        if (
            $post->media_type === 'image' &&
            $mediaUrl
        ) {
            return $this->request(
                'facebook',
                fn () => Http::asForm()
                    ->timeout(45)
                    ->post(
                        "https://graph.facebook.com/"
                        .self::META_VERSION
                        ."/{$pageId}/photos",
                        [
                            'url' => $mediaUrl,
                            'caption' => $caption,
                            'access_token' => $token,
                            'published' => 'true',
                        ]
                    ),
                fn ($response) => [
                    'external_post_id' =>
                        (string) (
                            $response->json('post_id')
                            ?: $response->json('id', '')
                        ),
                ]
            );
        }

        return $this->request(
            'facebook',
            fn () => Http::asForm()
                ->timeout(30)
                ->post(
                    "https://graph.facebook.com/"
                    .self::META_VERSION
                    ."/{$pageId}/feed",
                    [
                        'message' => $caption,
                        'access_token' => $token,
                    ]
                ),
            fn ($response) => [
                'external_post_id' =>
                    (string) $response->json('id', ''),
            ]
        );
    }

    private function publishInstagram(
        SocialMediaPost $post,
        string $token,
        string $instagramUserId
    ): array {
        if ($instagramUserId === '') {
            return $this->fallback(
                'Instagram professional account ID is missing.'
            );
        }

        $mediaUrl = $this->mediaUrl($post);

        if (! $mediaUrl) {
            return $this->fallback(
                'Instagram automatic publishing requires public media.'
            );
        }

        $caption = trim($post->shareText());

        try {
            $fields = [
                'caption' => $caption,
                'access_token' => $token,
            ];

            if ($post->media_type === 'video') {
                $fields['media_type'] = 'REELS';
                $fields['video_url'] = $mediaUrl;
            } else {
                $fields['image_url'] = $mediaUrl;
            }

            $container = Http::asForm()
                ->timeout(45)
                ->post(
                    "https://graph.facebook.com/"
                    .self::META_VERSION
                    ."/{$instagramUserId}/media",
                    $fields
                );

            if (! $container->successful()) {
                return $this->httpFailure(
                    'instagram',
                    $container
                );
            }

            $creationId = (string) $container->json('id', '');

            if ($creationId === '') {
                return $this->fallback(
                    'Instagram did not return a media container ID.'
                );
            }

            if ($post->media_type === 'video') {
                $ready = false;

                for ($attempt = 0; $attempt < 8; $attempt++) {
                    sleep(2);

                    $status = Http::get(
                        "https://graph.facebook.com/"
                        .self::META_VERSION
                        ."/{$creationId}",
                        [
                            'fields' => 'status_code',
                            'access_token' => $token,
                        ]
                    );

                    if (
                        $status->successful() &&
                        $status->json('status_code') === 'FINISHED'
                    ) {
                        $ready = true;
                        break;
                    }

                    if (
                        $status->successful() &&
                        $status->json('status_code') === 'ERROR'
                    ) {
                        break;
                    }
                }

                if (! $ready) {
                    return [
                        'published' => false,
                        'mode' => 'automatic',
                        'provider' => 'instagram',
                        'error' =>
                            'Instagram media is still processing; retry or post manually.',
                        'container_id' => $creationId,
                    ];
                }
            }

            $publish = Http::asForm()
                ->timeout(45)
                ->post(
                    "https://graph.facebook.com/"
                    .self::META_VERSION
                    ."/{$instagramUserId}/media_publish",
                    [
                        'creation_id' => $creationId,
                        'access_token' => $token,
                    ]
                );

            if (! $publish->successful()) {
                return $this->httpFailure(
                    'instagram',
                    $publish
                );
            }

            return [
                'published' => true,
                'mode' => 'automatic',
                'provider' => 'instagram',
                'external_post_id' =>
                    (string) $publish->json('id', ''),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'published' => false,
                'mode' => 'automatic',
                'provider' => 'instagram',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function publishLinkedIn(
        SocialMediaPost $post,
        string $token,
        string $authorUrn
    ): array {
        if ($authorUrn === '') {
            return $this->fallback(
                'LinkedIn author URN is missing.'
            );
        }

        $body = [
            'author' => $authorUrn,
            'commentary' => trim($post->shareText()),
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ];

        return $this->request(
            'linkedin',
            fn () => Http::asJson()
                ->acceptJson()
                ->withToken($token)
                ->withHeaders([
                    'LinkedIn-Version' => self::LINKEDIN_VERSION,
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->timeout(30)
                ->post(
                    'https://api.linkedin.com/rest/posts',
                    $body
                ),
            function ($response) {
                return [
                    'external_post_id' =>
                        (string) (
                            $response->header('x-restli-id')
                            ?: $response->header('X-RestLi-Id')
                        ),
                ];
            }
        );
    }

    private function publishTikTok(
        SocialMediaPost $post,
        string $token
    ): array {
        $mediaUrl = $this->mediaUrl($post);

        if (! $mediaUrl) {
            return $this->fallback(
                'TikTok Direct Post requires video/photo media from an approved public URL.'
            );
        }

        try {
            if ($post->media_type === 'image') {
                $response = Http::asJson()
                    ->acceptJson()
                    ->withToken($token)
                    ->timeout(45)
                    ->post(
                        'https://open.tiktokapis.com/v2/post/publish/content/init/',
                        [
                            'post_info' => [
                                'title' => trim($post->title),
                                'description' =>
                                    trim($post->shareText()),
                                'privacy_level' => 'PUBLIC_TO_EVERYONE',
                                'disable_comment' => false,
                                'auto_add_music' => true,
                            ],
                            'source_info' => [
                                'source' => 'PULL_FROM_URL',
                                'photo_cover_index' => 0,
                                'photo_images' => [$mediaUrl],
                            ],
                            'post_mode' => 'DIRECT_POST',
                            'media_type' => 'PHOTO',
                        ]
                    );
            } else {
                $response = Http::asJson()
                    ->acceptJson()
                    ->withToken($token)
                    ->timeout(45)
                    ->post(
                        'https://open.tiktokapis.com/v2/post/publish/video/init/',
                        [
                            'post_info' => [
                                'title' => trim($post->shareText()),
                                'privacy_level' => 'PUBLIC_TO_EVERYONE',
                                'disable_duet' => false,
                                'disable_comment' => false,
                                'disable_stitch' => false,
                                'video_cover_timestamp_ms' => 1000,
                            ],
                            'source_info' => [
                                'source' => 'PULL_FROM_URL',
                                'video_url' => $mediaUrl,
                            ],
                        ]
                    );
            }

            if (! $response->successful()) {
                return $this->httpFailure(
                    'tiktok',
                    $response
                );
            }

            $publishId = (string) $response->json(
                'data.publish_id',
                ''
            );

            return [
                /*
                 * TikTok processing is asynchronous. `submitted` is true,
                 * while the scheduler does not claim final publication.
                 */
                'published' => $publishId !== '',
                'submitted' => $publishId !== '',
                'mode' => 'automatic',
                'provider' => 'tiktok',
                'external_post_id' => $publishId ?: null,
                'note' =>
                    'TikTok accepted the Direct Post request for processing.',
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'published' => false,
                'mode' => 'automatic',
                'provider' => 'tiktok',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function mediaUrl(SocialMediaPost $post): ?string
    {
        $path = trim((string) ($post->media_path ?? ''));

        if ($path === '') {
            return null;
        }

        if (
            str_starts_with($path, 'https://') ||
            str_starts_with($path, 'http://')
        ) {
            return $path;
        }

        try {
            return url(Storage::url($path));
        } catch (Throwable) {
            return url('/storage/'.ltrim($path, '/'));
        }
    }

    private function request(
        string $provider,
        callable $request,
        callable $successData
    ): array {
        try {
            $response = $request();

            if (! $response->successful()) {
                return $this->httpFailure(
                    $provider,
                    $response
                );
            }

            return array_merge(
                [
                    'published' => true,
                    'mode' => 'automatic',
                    'provider' => $provider,
                ],
                $successData($response)
            );
        } catch (Throwable $e) {
            report($e);

            return [
                'published' => false,
                'mode' => 'automatic',
                'provider' => $provider,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function httpFailure(
        string $provider,
        $response
    ): array {
        return [
            'published' => false,
            'mode' => 'automatic',
            'provider' => $provider,
            'http_status' => $response->status(),
            'error' =>
                $response->json('error.message')
                ?? $response->json('error.message')
                ?? $response->json('message')
                ?? $response->json('detail')
                ?? 'The provider rejected the publishing request.',
        ];
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

    private function fallback(string $reason): array
    {
        return [
            'published' => false,
            'mode' => 'manual_share',
            'reason' => $reason,
        ];
    }
}
