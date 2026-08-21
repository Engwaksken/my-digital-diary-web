<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SocialMediaReportService;
use App\Services\SocialMediaAnalyticsService;
use Illuminate\Http\Request;

class SocialMediaReportController extends Controller
{
    public function __construct(private readonly SocialMediaReportService $reports) {}


    public function sync(Request $request, SocialMediaAnalyticsService $analytics)
    {
        return response()->json([
            'data' => $analytics->syncForUser($request->user(), 150),
        ]);
    }

    public function index(Request $request)
    {
        $v = $request->validate([
            'period' => ['nullable','in:today,week,month,year,all,custom'],
            'platform' => ['nullable','in:instagram,facebook,x,tiktok,linkedin,whatsapp_status,whatsapp_channel'],
            'status' => ['nullable','in:draft,scheduled,ready_to_share,published,failed'],
            'from' => ['nullable','date'],
            'to' => ['nullable','date','after_or_equal:from'],
        ]);

        $report = $this->reports->report(
            $request->user(),
            $v['period'] ?? 'month',
            $v['platform'] ?? null,
            $v['status'] ?? null,
            $v['from'] ?? null,
            $v['to'] ?? null
        );

        return response()->json(['data' => [
            'period' => $report['period'],
            'period_start' => $report['period_start'],
            'period_end' => $report['period_end'],
            'summary' => $report['summary'],
            'platform_breakdown' => $report['platform_breakdown'],
            'analytics' => $report['analytics'],
            'sync' => $report['sync'] ?? [],
            'posts' => $report['posts']->values()->all(),
        ]]);
    }
}
