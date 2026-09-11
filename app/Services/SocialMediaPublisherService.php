<?php

namespace App\Services;

use App\Models\SocialMediaPost;
use App\Models\SocialMediaProviderConfig;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SocialMediaPublisherService
{
    private const META_VERSION = 'v25.0';

    private function fullPostText(SocialMediaPost $post): string
    {
        return trim($post->shareText());
    }
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

        if (! $account) {
            return $this->fallback(
                'No active account is connected for this platform.'
            );
        }

        if (! (bool) ($account->auto_publish_enabled ?? false)) {
            return $this->fallback(
                'Automatic publishing is disabled for this account.'
            );
        }

        $provider = SocialMediaProviderConfig::enabledFor($platform);

        if (! $provider) {
            return $this->fallback(
                'Automatic publishing is not enabled by the administrator for this platform.'
            );
        }

        if (in_array($platform, ['whatsapp_status', 'whatsapp_channel'], true)) {
            return $this->publishWhatsAppProvider(
                $post,
                $platform,
                $account,
                $provider
            );
        }

        /*
         * Official social APIs generally require the user's own OAuth access
         * token. The administrator owns the application/provider credentials,
         * but those do not replace the user's authorisation.
         */
        $token = $this->decryptToken(
            $account->oauth_access_token ?? null
        );

        if ($provider->connection_mode === 'user_oauth' && ! $token) {
            return $this->fallback(
                'This social account still needs user OAuth authorisation.'
            );
        }

        return match ($platform) {
            'x' => $this->publishX($post, (string) $token),
            'facebook' => $this->publishFacebook(
                $post,
                (string) $token,
                (string) ($account->external_account_id ?? '')
            ),
            'instagram' => $this->publishInstagram(
                $post,
                (string) $token,
                (string) ($account->external_account_id ?? '')
            ),
            'linkedin' => $this->publishLinkedIn(
                $post,
                (string) $token,
                (string) ($account->external_account_id ?? '')
            ),
            'tiktok' => $this->publishTikTok($post, (string) $token),
            default => $this->fallback('Unsupported platform.'),
        };
    }

    private function publishWhatsAppProvider(
        SocialMediaPost $post,
        string $platform,
        object $account,
        SocialMediaProviderConfig $provider
    ): array {
        $session = trim((string) (
            $account->provider_account_ref
            ?? $account->external_account_id
            ?? ''
        ));

        if ($session === '') {
            return $this->fallback(
                'WhatsApp automatic posting needs your provider session/account reference.'
            );
        }

        $driver = strtolower((string) $provider->driver);

        return match ($driver) {
            'whatsscale' => $this->publishWhatsScaleStatus(
                $post,
                $session,
                $provider
            ),
            'waha' => $this->publishWahaStatusOrChannel(
                $post,
                $platform,
                $session,
                (string) ($account->external_account_id ?? ''),
                $provider
            ),
            default => $this->publishGenericProvider(
                $post,
                $platform,
                $session,
                $account,
                $provider
            ),
        };
    }

    private function publishWhatsScaleStatus(
        SocialMediaPost $post,
        string $session,
        SocialMediaProviderConfig $provider
    ): array {
        $baseUrl = rtrim((string) $provider->base_url, '/');
        $apiKey = $provider->decryptedApiKey();

        if ($baseUrl === '' || ! $apiKey) {
            return $this->fallback(
                'WhatsScale is not fully configured by the administrator.'
            );
        }

        $mediaUrl = $this->mediaUrl($post);
        $text = $this->fullPostText($post);

        // Media is sent once with the full post text in the caption field.
        // Do not send a separate text status before/after this request.
        if ($post->media_type === 'video' && $mediaUrl) {
            $endpoint = $baseUrl.'/api/status/video';
            $payload = [
                'session' => $session,
                'file' => $mediaUrl,
                'caption' => $text,
            ];
        } elseif ($post->media_type === 'image' && $mediaUrl) {
            $endpoint = $baseUrl.'/api/status/image';
            $payload = [
                'session' => $session,
                'file' => $mediaUrl,
                'caption' => $text,
            ];
        } else {
            $endpoint = $baseUrl.'/api/status/text';
            $payload = [
                'session' => $session,
                'text' => $text,
                'backgroundColor' => data_get(
                    $provider->settings,
                    'background_color',
                    '#25D366'
                ),
            ];
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withHeaders(['X-Api-Key' => $apiKey])
                ->timeout($post->media_type === 'video' ? 90 : 45)
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                return $this->httpFailure('whatsscale', $response);
            }

            return [
                'published' => true,
                'mode' => 'automatic',
                'provider' => $provider->provider_name,
                'external_post_id' => (string) (
                    $response->json('key.id')
                    ?: $response->json('jobId', '')
                ),
                'response' => $response->json(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'published' => false,
                'mode' => 'automatic',
                'provider' => $provider->provider_name,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function publishWahaStatusOrChannel(
        SocialMediaPost $post,
        string $platform,
        string $session,
        string $channelId,
        SocialMediaProviderConfig $provider
    ): array {
        $baseUrl = rtrim((string) $provider->base_url, '/');
        $apiKey = $provider->decryptedApiKey();

        if ($baseUrl === '') {
            return $this->fallback(
                'WAHA Base URL is not configured by the administrator.'
            );
        }

        $request = Http::asJson()
            ->acceptJson()
            ->timeout($post->media_type === 'video' ? 90 : 45);

        if ($apiKey) {
            $request = $request->withHeaders([
                $provider->auth_header ?: 'X-Api-Key' => $apiKey,
            ]);
        }

        $mediaUrl = $this->mediaUrl($post);
        $text = $this->fullPostText($post);

        // For WhatsApp media, use one media request with caption. This
        // prevents a separate text message followed by a separate image/video.
        if ($platform === 'whatsapp_channel') {
            if ($channelId === '') {
                return $this->fallback(
                    'WhatsApp Channel ID is required for automatic Channel posting.'
                );
            }

            /*
             * WAHA channels are newsletter chats. Use its send APIs with the
             * channel ID as chatId where supported by the installed driver.
             */
            if ($post->media_type === 'image' && $mediaUrl) {
                $endpoint = $baseUrl.'/api/sendImage';
                $payload = [
                    'session' => $session,
                    'chatId' => $channelId,
                    'file' => ['url' => $mediaUrl],
                    'caption' => $text,
                ];
            } elseif ($post->media_type === 'video' && $mediaUrl) {
                $endpoint = $baseUrl.'/api/sendVideo';
                $payload = [
                    'session' => $session,
                    'chatId' => $channelId,
                    'file' => ['url' => $mediaUrl],
                    'caption' => $text,
                ];
            } else {
                $endpoint = $baseUrl.'/api/sendText';
                $payload = [
                    'session' => $session,
                    'chatId' => $channelId,
                    'text' => $text,
                ];
            }
        } else {
            if ($post->media_type === 'image' && $mediaUrl) {
                $endpoint = $baseUrl.'/api/'.rawurlencode($session).'/status/image';
                $payload = [
                    'file' => ['url' => $mediaUrl],
                    'caption' => $text,
                ];
            } elseif ($post->media_type === 'video' && $mediaUrl) {
                $endpoint = $baseUrl.'/api/'.rawurlencode($session).'/status/video';
                $payload = [
                    'file' => ['url' => $mediaUrl],
                    'caption' => $text,
                ];
            } else {
                $endpoint = $baseUrl.'/api/'.rawurlencode($session).'/status/text';
                $payload = ['text' => $text];
            }
        }

        try {
            $response = $request->post($endpoint, $payload);

            if (! $response->successful()) {
                return $this->httpFailure('waha', $response);
            }

            return [
                'published' => true,
                'mode' => 'automatic',
                'provider' => $provider->provider_name,
                'external_post_id' => (string) (
                    $response->json('id')
                    ?: $response->json('key.id', '')
                ),
                'response' => $response->json(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'published' => false,
                'mode' => 'automatic',
                'provider' => $provider->provider_name,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function publishGenericProvider(
        SocialMediaPost $post,
        string $platform,
        string $session,
        object $account,
        SocialMediaProviderConfig $provider
    ): array {
        $baseUrl = rtrim((string) $provider->base_url, '/');
        $path = trim((string) data_get(
            $provider->settings,
            'publish_endpoint',
            ''
        ));

        if ($baseUrl === '' || $path === '') {
            return $this->fallback(
                'The administrator has not completed this provider configuration.'
            );
        }

        $request = Http::asJson()->acceptJson()->timeout(60);
        $key = $provider->decryptedApiKey();

        if ($key && $provider->auth_type === 'bearer') {
            $request = $request->withToken($key);
        } elseif ($key && $provider->auth_type === 'header') {
            $request = $request->withHeaders([
                $provider->auth_header ?: 'X-Api-Key' => $key,
            ]);
        }

        $mediaUrl = $this->mediaUrl($post);
        $fullText = $this->fullPostText($post);
        $hasMedia = in_array($post->media_type, ['image', 'video'], true)
            && filled($mediaUrl);

        /*
         * For media posts, send the full copy as the media caption rather
         * than as a separate text message. This allows WhatsApp-compatible
         * providers and other generic connectors to render:
         *
         *   [image/video]
         *   title
         *   caption
         *   CTA
         *   link
         *   hashtags
         *
         * as one combined media post.
         */
        $payload = [
            'platform' => $platform,
            'session' => $session,
            'account_id' => $account->external_account_id ?? null,
            'username' => $account->username ?? null,
            'text' => $hasMedia ? null : $fullText,
            'caption' => $hasMedia ? $fullText : null,
            'media_type' => $post->media_type,
            'media_url' => $mediaUrl,
            'link_url' => method_exists($post, 'attachedLink')
                ? $post->attachedLink()
                : null,
            'combine_media_and_caption' => $hasMedia,
            'send_as_single_media_message' => $hasMedia,
        ];

        try {
            $response = $request->post(
                $baseUrl.'/'.ltrim($path, '/'),
                $payload
            );

            if (! $response->successful()) {
                return $this->httpFailure(
                    $provider->provider_name,
                    $response
                );
            }

            return [
                'published' => (bool) $response->json('published', true),
                'mode' => 'automatic',
                'provider' => $provider->provider_name,
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
                'provider' => $provider->provider_name,
                'error' => $e->getMessage(),
            ];
        }
    }


    private function publishX(
        SocialMediaPost $post,
        string $token
    ): array {
        $text = $this->fullPostText($post);

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

        $caption = $this->fullPostText($post);
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

        if ($post->media_type === 'video' && $mediaUrl) {
            return $this->request(
                'facebook',
                fn () => Http::asForm()
                    ->timeout(90)
                    ->post(
                        "https://graph.facebook.com/"
                        .self::META_VERSION
                        ."/{$pageId}/videos",
                        [
                            'file_url' => $mediaUrl,
                            'description' => $caption,
                            'access_token' => $token,
                        ]
                    ),
                fn ($response) => [
                    'external_post_id' => (string) $response->json('id', ''),
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

        $caption = $this->fullPostText($post);

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
            'commentary' => $this->fullPostText($post),
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
                                    $this->fullPostText($post),
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
                                'title' => $this->fullPostText($post),
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
            'error' => $reason,
        ];
    }
}
