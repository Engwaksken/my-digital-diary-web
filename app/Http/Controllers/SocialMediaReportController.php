<?php

namespace App\Http\Controllers;

use App\Services\SocialMediaReportService;
use App\Services\SocialMediaAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialMediaReportController extends Controller
{
    public function __construct(private readonly SocialMediaReportService $reports) {}

    public function index(Request $request)
    {
        $f = $this->filters($request);
        $report = $this->reports->report($request->user(), $f['period'], $f['platform'], $f['status'], $f['from'], $f['to']);
        return view('social-media-planner.reports', compact('report','f'));
    }


    public function sync(Request $request, SocialMediaAnalyticsService $analytics)
    {
        $result = $analytics->syncForUser($request->user(), 150);

        $message = sprintf(
            'Analytics sync finished: %d synced, %d failed, %d skipped.',
            $result['synced'] ?? 0,
            $result['failed'] ?? 0,
            $result['skipped'] ?? 0,
        );

        return back()->with(
            ($result['failed'] ?? 0) > 0 && ($result['synced'] ?? 0) === 0 ? 'error' : 'success',
            $message
        );
    }

    public function pdf(Request $request)
    {
        $f = $this->filters($request);

        $report = $this->reports->report(
            $request->user(),
            $f['period'],
            $f['platform'],
            $f['status'],
            $f['from'],
            $f['to']
        );

        $filename = sprintf(
            'social-media-report-%s-to-%s.pdf',
            $report['period_start'],
            $report['period_end']
        );

        return Pdf::loadView(
            'social-media-planner.reports-pdf',
            compact('report', 'f')
        )
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function csv(Request $request): StreamedResponse
    {
        $f = $this->filters($request);
        $report = $this->reports->report($request->user(), $f['period'], $f['platform'], $f['status'], $f['from'], $f['to']);

        return response()->streamDownload(function () use ($report) {
            $h = fopen('php://output', 'w');
            fputcsv($h, [
                'Title','Platform','Status','Scheduled At','Published At',
                'Views','Reach','Impressions','Likes','Comments','Shares','Saves','Clicks',
                'Engagements','Engagement Rate %','Last Synced','External Post ID','Caption','Hashtags'
            ]);

            foreach ($report['posts'] as $post) {
                $metrics = $post->metrics;

                if ($metrics->isEmpty()) {
                    fputcsv($h, [
                        $post->title,
                        implode(', ', (array) ($post->platforms ?? [])),
                        $post->status,
                        optional($post->scheduled_at)->format('Y-m-d H:i:s'),
                        optional($post->published_at)->format('Y-m-d H:i:s'),
                        0,0,0,0,0,0,0,0,0,0,'','',
                        $post->caption,
                        $post->hashtags,
                    ]);
                    continue;
                }

                foreach ($metrics as $metric) {
                    fputcsv($h, [
                        $post->title,
                        $metric->platform,
                        $post->status,
                        optional($post->scheduled_at)->format('Y-m-d H:i:s'),
                        optional($post->published_at)->format('Y-m-d H:i:s'),
                        $metric->views,
                        $metric->reach,
                        $metric->impressions,
                        $metric->likes,
                        $metric->comments,
                        $metric->shares,
                        $metric->saves,
                        $metric->clicks,
                        $metric->engagements,
                        $metric->engagement_rate,
                        optional($metric->synced_at)->format('Y-m-d H:i:s'),
                        $metric->external_post_id,
                        $post->caption,
                        $post->hashtags,
                    ]);
                }
            }
            fclose($h);
        }, 'social-media-report-'.$report['period_start'].'-'.$report['period_end'].'.csv');
    }

    private function filters(Request $request): array
    {
        $v = $request->validate([
            'period' => ['nullable','in:today,week,month,year,all,custom'],
            'platform' => ['nullable','in:instagram,facebook,x,tiktok,linkedin,whatsapp_status,whatsapp_channel'],
            'status' => ['nullable','in:draft,scheduled,ready_to_share,published,failed'],
            'from' => ['nullable','date'],
            'to' => ['nullable','date','after_or_equal:from'],
        ]);

        return [
            'period' => $v['period'] ?? 'month',
            'platform' => $v['platform'] ?? null,
            'status' => $v['status'] ?? null,
            'from' => $v['from'] ?? null,
            'to' => $v['to'] ?? null,
        ];
    }
}
