<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SocialMediaPost;
use App\Services\SocialMediaAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SocialMediaAnalyticsController extends Controller
{
    public function show(
        Request $request,
        SocialMediaPost $socialMediaPost
    ): View {
        abort_unless(
            (int) $socialMediaPost->user_id === (int) $request->user()->id,
            403
        );

        $metrics = $this->metricsForPost($socialMediaPost);
        $snapshots = $this->snapshotsForPost($socialMediaPost);

        return view('social-media-planner.analytics', [
            'post' => $socialMediaPost,
            'metrics' => $metrics,
            'snapshots' => $snapshots,
            'analyticsStorageReady' => $this->metricsStorageReady(),
        ]);
    }

    public function sync(
        Request $request,
        SocialMediaPost $socialMediaPost
    ): RedirectResponse {
        abort_unless(
            (int) $socialMediaPost->user_id === (int) $request->user()->id,
            403
        );

        return back()->with(
            'success',
            'Analytics refresh requested for this post. Connected provider metrics will appear when available.'
        );
    }

    public function update(
        Request $request,
        SocialMediaPost $socialMediaPost,
        SocialMediaAnalyticsService $analytics
    ): RedirectResponse {
        abort_unless(
            (int) $socialMediaPost->user_id === (int) $request->user()->id,
            403
        );

        abort_unless(
            $this->metricsStorageReady(),
            503,
            'Social media analytics storage is not ready. Run the latest migrations.'
        );

        $data = $request->validate([
            'platform' => ['required', Rule::in([
                'instagram', 'facebook', 'x', 'tiktok', 'linkedin',
                'whatsapp_status', 'whatsapp_channel',
            ])],
            'views' => ['nullable', 'integer', 'min:0'],
            'reach' => ['nullable', 'integer', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'likes' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'integer', 'min:0'],
            'shares' => ['nullable', 'integer', 'min:0'],
            'saves' => ['nullable', 'integer', 'min:0'],
            'clicks' => ['nullable', 'integer', 'min:0'],
            'replies' => ['nullable', 'integer', 'min:0'],
            'external_post_id' => ['nullable', 'string', 'max:255'],
        ]);

        $analytics->saveManual(
            $socialMediaPost,
            $data['platform'],
            $data
        );

        return back()->with('success', 'Post performance updated.');
    }

    private function metricsForPost(SocialMediaPost $post): Collection
    {
        if (! $this->metricsStorageReady()) {
            return collect();
        }

        return DB::table('social_media_post_metrics')
            ->where('social_media_post_id', $post->id)
            ->orderBy('platform')
            ->get()
            ->map(function (object $row): object {
                if (property_exists($row, 'raw_metrics') && is_string($row->raw_metrics)) {
                    $decoded = json_decode($row->raw_metrics, true);
                    $row->raw_metrics = is_array($decoded) ? $decoded : [];
                }

                if (property_exists($row, 'synced_at') && $row->synced_at) {
                    try {
                        $row->synced_at = \Illuminate\Support\Carbon::parse($row->synced_at);
                    } catch (\Throwable) {
                        $row->synced_at = null;
                    }
                }

                return $row;
            });
    }

    private function snapshotsForPost(SocialMediaPost $post): Collection
    {
        if (! Schema::hasTable('social_media_metric_snapshots') ||
            ! Schema::hasColumn('social_media_metric_snapshots', 'social_media_post_id')) {
            return collect();
        }

        $query = DB::table('social_media_metric_snapshots')
            ->where('social_media_post_id', $post->id);

        if (Schema::hasColumn('social_media_metric_snapshots', 'captured_at')) {
            $query->orderByDesc('captured_at');
        } else {
            $query->orderByDesc('id');
        }

        return $query->limit(100)->get();
    }

    private function metricsStorageReady(): bool
    {
        if (! Schema::hasTable('social_media_post_metrics')) {
            return false;
        }

        foreach ([
            'social_media_post_id',
            'platform',
            'views',
            'reach',
            'impressions',
            'likes',
            'comments',
            'shares',
            'saves',
            'clicks',
            'replies',
            'engagements',
            'engagement_rate',
        ] as $column) {
            if (! Schema::hasColumn('social_media_post_metrics', $column)) {
                return false;
            }
        }

        return true;
    }
}
