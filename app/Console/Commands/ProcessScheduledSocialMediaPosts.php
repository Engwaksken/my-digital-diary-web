<?php

namespace App\Console\Commands;

use App\Models\SocialMediaPost;
use App\Notifications\SocialMediaPostingNotification;
use App\Services\FcmService;
use App\Services\SocialMediaPublisherService;
use App\Services\SocialMediaAnalyticsService;
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
        $this->sendUpcomingReminders($fcm);

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
                continue;
            }

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

                $result = $publisher->publish($post);

                // Persist every provider post ID immediately so the analytics
                // scheduler can start fetching engagement metrics automatically.
                $analytics->registerPublishingResults($post, $result);

                $post->forceFill([
                    'publishing_results' => $result,
                ]);

                if (($result['published'] ?? false) === true) {
                    $post->status = 'published';
                    $post->published_at = now();
                    $post->last_error = null;
                    $post->posting_notification_sent_at = now();
                    $post->save();

                    $this->notify(
                        $post,
                        $fcm,
                        'published',
                        'Social media post published',
                        "“{$post->title}” was posted successfully."
                    );

                    continue;
                }

                if (($result['partial'] ?? false) === true) {
                    $post->last_error =
                        'Some platforms published successfully while others require attention.';
                    $post->save();

                    $this->moveToReady(
                        $post,
                        $fcm,
                        'partial_failure',
                        'Some platforms need your attention',
                        "“{$post->title}” posted to some platforms, but the remaining platforms are ready for you to complete manually."
                    );

                    continue;
                }
            }

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

    private function sendUpcomingReminders(FcmService $fcm): void
    {
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
            ->limit(250)
            ->get();

        foreach ($posts as $post) {
            if (! $post->user) {
                continue;
            }

            $post->forceFill([
                'reminder_sent_at' => now(),
            ])->save();

            $mode = ($post->posting_mode ?? 'manual') === 'automatic'
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
        $results['became_ready_at'] = now()->toIso8601String();

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
                'Could not save social posting notification.',
                [
                    'post_id' => $post->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]
            );
        }

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
                ],
            );
        } catch (Throwable $e) {
            Log::warning(
                'Could not send social posting push notification.',
                [
                    'post_id' => $post->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
