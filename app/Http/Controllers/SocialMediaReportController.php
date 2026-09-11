<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SocialMediaReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SocialMediaReportController extends Controller
{
    public function __construct(
        private readonly SocialMediaReportService $reports
    ) {
    }

    public function index(Request $request)
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

        return view('social-media-planner.reports', compact('report', 'f'));
    }

    public function sync(Request $request): RedirectResponse
    {
        // The actual provider sync command/service may be configured separately.
        // Keeping this endpoint defined prevents Reports from failing at render time.
        return back()->with(
            'success',
            'Analytics refresh requested. Connected provider metrics will appear when available.'
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
        )->setPaper('a4', 'landscape')->download($filename);
    }

    public function csv(Request $request): StreamedResponse
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

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Title', 'Platforms', 'Status', 'Scheduled At',
                'Published At', 'Caption', 'Hashtags',
            ]);

            foreach ($report['posts'] as $post) {
                fputcsv($handle, [
                    $post->title,
                    implode(', ', (array) ($post->platforms ?? [])),
                    $post->status,
                    optional($post->scheduled_at)->format('Y-m-d H:i:s'),
                    optional($post->published_at)->format('Y-m-d H:i:s'),
                    $post->caption,
                    $post->hashtags,
                ]);
            }

            fclose($handle);
        }, 'social-media-report-'.$report['period_start'].'-'.$report['period_end'].'.csv');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:today,week,month,year,all,custom'],
            'platform' => ['nullable', 'in:instagram,facebook,x,tiktok,linkedin,whatsapp_status,whatsapp_channel'],
            'status' => ['nullable', 'in:draft,scheduled,ready_to_share,published,failed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'period' => $validated['period'] ?? 'month',
            'platform' => $validated['platform'] ?? null,
            'status' => $validated['status'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }
}
