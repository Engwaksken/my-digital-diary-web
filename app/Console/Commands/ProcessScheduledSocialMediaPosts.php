<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SocialMediaPost;
use App\Notifications\SocialMediaPostingNotification;
use App\Services\FcmService;
use App\Services\SocialMediaAnalyticsService;
use App\Services\SocialMediaPublisherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessScheduledSocialMediaPosts extends Command
{
    protected $signature = 'social-media:process-scheduled';

    protected $description =
        'Send social reminders and process scheduled automatic posts';

    public function handle(
        FcmService $fcm,
        SocialMediaPublisherService $publisher,
        SocialMediaAnalyticsService $analytics
    ): int {
        /*
        |--------------------------------------------------------------------------
        | Upcoming reminders
        |--------------------------------------------------------------------------
        */
        $this->sendUpcomingReminders($fcm);

        /*
        |--------------------------------------------------------------------------
        | Due scheduled posts
        |--------------------------------------------------------------------------
        */
        $posts = SocialMediaPost::query()
            ->with('user.deviceTokens')
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(250)
            ->get();

        foreach ($posts as $post) {
            $user = $post->user;

            if (! $user) {
                Log::warning(
                    'Scheduled social media post skipped because user was not found.',
                    [
                        'post_id' => $post->id,
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Automatic publishing
            |--------------------------------------------------------------------------
            */
            if (($post->posting_mode ?? 'manual') === 'automatic') {
                $post->forceFill([
                    'posting_started_at' => now(),
                ])->save();

                $this->notify(
                    $post,
                    $fcm,
                    'posting',
                    'Posting your social media content',
                    "“{$post->title}” is being posted to the selected connected platforms."
                );

                try {
                    $result = $publisher->publish($post);
                } catch (Throwable $e) {
                    Log::error(
                        'Automatic social media publishing failed.',
                        [
                            'post_id' => $post->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]
                    );

                    $post->forceFill([
                        'last_error' => $e->getMessage(),
                    ])->save();

                    $this->moveToReady(
                        $post,
                        $fcm,
                        'publishing_failed',
                        'Automatic posting failed',
                        "“{$post->title}” could not be posted automatically and is now ready for you to complete manually."
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Save publishing results immediately
                |--------------------------------------------------------------------------
                |
                | Save provider results first so successful provider post IDs are not
                | lost even if analytics registration fails afterwards.
                |
                */
                $post->forceFill([
                    'publishing_results' => $result,
                ])->save();

                /*
                |--------------------------------------------------------------------------
                | Analytics registration
                |--------------------------------------------------------------------------
                |
                | Older SocialMediaAnalyticsService installations may not yet contain
                | registerPublishingResults().
                |
                | Analytics registration must never cause successful social publishing
                | to fail.
                |
                */
                $this->registerAnalyticsResults(
                    $analytics,
                    $post,
                    $result
                );

                /*
                |--------------------------------------------------------------------------
                | Fully published
                |--------------------------------------------------------------------------
                */
                if (($result['published'] ?? false) === true) {
                    $post->forceFill([
                        'status' => 'published',
                        'published_at' => now(),
                        'last_error' => null,
                        'posting_notification_sent_at' => now(),
                    ])->save();

                    $this->notify(
                        $post,
                        $fcm,
                        'published',
                        'Social media post published',
                        "“{$post->title}” was posted successfully."
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Partial publishing
                |--------------------------------------------------------------------------
                */
                if (($result['partial'] ?? false) === true) {
                    $post->forceFill([
                        'last_error' =>
                            'Some platforms published successfully while others require attention.',
                    ])->save();

                    $this->moveToReady(
                        $post,
                        $fcm,
                        'partial_failure',
                        'Some platforms need your attention',
                        "“{$post->title}” posted to some platforms, but the remaining platforms are ready for you to complete manually."
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Automatic publishing returned no success
                |--------------------------------------------------------------------------
                */
                $post->forceFill([
                    'last_error' =>
                        $this->extractPublishingError($result)
                        ?? 'Automatic publishing did not complete successfully.',
                ])->save();

                $this->moveToReady(
                    $post,
                    $fcm,
                    'automatic_fallback',
                    'Post ready for manual publishing',
                    "“{$post->title}” could not be completed automatically and is ready for you to post manually."
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Manual posting mode
            |--------------------------------------------------------------------------
            */
            $this->moveToReady(
                $post,
                $fcm,
                'ready_to_post',
                'Social media post ready',
                "“{$post->title}” is ready to post now."
            );
        }

        return self::SUCCESS;
    }

    /**
     * Send reminders roughly 30 minutes before the scheduled posting time.
     */
    private function sendUpcomingReminders(
        FcmService $fcm
    ): void {
        $posts = SocialMediaPost::query()
            ->with('user.deviceTokens')
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereNull('reminder_sent_at')
            ->whereBetween(
                'scheduled_at',
                [
                    now()->addMinutes(29),
                    now()->addMinutes(31),
                ]
            )
            ->orderBy('scheduled_at')
            ->limit(250)
            ->get();

        foreach ($posts as $post) {
            if (! $post->user) {
                continue;
            }

            /*
             * Mark the reminder first so an email/push failure does not cause the
             * scheduler to repeatedly send the same reminder every minute.
             */
            $post->forceFill([
                'reminder_sent_at' => now(),
            ])->save();

            $mode =
                ($post->posting_mode ?? 'manual') === 'automatic'
                    ? 'will be posted automatically'
                    : 'will be ready for you to post';

            $this->notify(
                $post,
                $fcm,
                'reminder',
                'Social media post in 30 minutes',
                "“{$post->title}” {$mode} in about 30 minutes."
            );
        }
    }

    /**
     * Safely register provider post IDs/results for analytics.
     *
     * Missing analytics support must never break publishing.
     */
    private function registerAnalyticsResults(
        SocialMediaAnalyticsService $analytics,
        SocialMediaPost $post,
        array $result
    ): void {
        if (! method_exists(
            $analytics,
            'registerPublishingResults'
        )) {
            Log::notice(
                'Social media analytics registration skipped because registerPublishingResults() is not available.',
                [
                    'post_id' => $post->id,
                ]
            );

            return;
        }

        try {
            $analytics->registerPublishingResults(
                $post,
                $result
            );
        } catch (Throwable $e) {
            Log::warning(
                'Could not register social media publishing results for analytics.',
                [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Move a post into manual-ready state.
     */
    private function moveToReady(
        SocialMediaPost $post,
        FcmService $fcm,
        string $event,
        string $title,
        string $message
    ): void {
        $results = is_array($post->publishing_results)
            ? $post->publishing_results
            : [];

        $results['fallback'] = 'ready_to_post';
        $results['became_ready_at'] =
            now()->toIso8601String();

        $post->forceFill([
            'status' => 'ready_to_share',
            'publishing_results' => $results,
        ])->save();

        $this->notify(
            $post,
            $fcm,
            $event,
            $title,
            $message
        );
    }

    /**
     * Persist normal notification and send FCM push.
     *
     * Neither notification channel is allowed to stop post processing.
     */
    private function notify(
        SocialMediaPost $post,
        FcmService $fcm,
        string $event,
        string $title,
        string $message
    ): void {
        $user = $post->user;

        if (! $user) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Database / email notification
        |--------------------------------------------------------------------------
        */
        try {
            $user->notify(
                new SocialMediaPostingNotification(
                    $post,
                    $event,
                    $title,
                    $message
                )
            );
        } catch (Throwable $e) {
            Log::warning(
                'Could not save/send social posting notification.',
                [
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile push notification
        |--------------------------------------------------------------------------
        */
        try {
            $fcm->sendToUser(
                $user,
                $title,
                $message,
                [
                    'type' => 'social_media_posting',
                    'event' => $event,
                    'post_id' => (string) $post->id,
                    'target' => 'social-media-planner',
                ]
            );
        } catch (Throwable $e) {
            Log::warning(
                'Could not send social posting push notification.',
                [
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Try to obtain a useful error message from publisher results.
     */
    private function extractPublishingError(
        array $result
    ): ?string {
        $candidates = [
            $result['error'] ?? null,
            $result['message'] ?? null,
            $result['last_error'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (
                is_string($candidate)
                && trim($candidate) !== ''
            ) {
                return trim($candidate);
            }
        }

        if (
            isset($result['errors'])
            && is_array($result['errors'])
            && $result['errors'] !== []
        ) {
            $messages = [];

            foreach ($result['errors'] as $error) {
                if (is_string($error)) {
                    $messages[] = $error;

                    continue;
                }

                if (is_array($error)) {
                    $message =
                        $error['message']
                        ?? $error['error']
                        ?? null;

                    if (
                        is_string($message)
                        && trim($message) !== ''
                    ) {
                        $messages[] = trim($message);
                    }
                }
            }

            if ($messages !== []) {
                return implode(
                    ' | ',
                    array_unique($messages)
                );
            }
        }

        return null;
    }
}