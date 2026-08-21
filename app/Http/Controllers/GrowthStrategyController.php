<?php

namespace App\Http\Controllers;

use App\Services\GrowthStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrowthStrategyController extends Controller
{
    public function __construct(private readonly GrowthStrategyService $growth) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->growth->dashboard($request->user())]);
    }

    public function joinChallenge(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->growth->joinChallenge($request->user())]);
    }

    public function referral(Request $request): JsonResponse
    {
        $data = $request->validate(['channel' => ['nullable','string','max:32']]);
        return response()->json(['data' => $this->growth->referralLink($request->user(), $data['channel'] ?? 'app')]);
    }

    public function invite(string $code): View
    {
        abort_unless($this->growth->markInviteClick($code), 404);
        return view('growth.invite', compact('code'));
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->growth->preferences($request->user())]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'morning_brief' => ['sometimes','boolean'],
            'evening_review' => ['sometimes','boolean'],
            'weekly_review' => ['sometimes','boolean'],
            'monthly_review' => ['sometimes','boolean'],
            'milestones' => ['sometimes','boolean'],
            'product_tips' => ['sometimes','boolean'],
            'referral_updates' => ['sometimes','boolean'],
            'morning_time' => ['sometimes','date_format:H:i'],
            'evening_time' => ['sometimes','date_format:H:i'],
            'timezone' => ['sometimes','timezone'],
        ]);
        return response()->json(['data' => $this->growth->updatePreferences($request->user(), $data)]);
    }

    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_name' => ['required','string','max:80'],
            'source' => ['nullable','string','max:50'],
            'meta' => ['nullable','array'],
        ]);
        $this->growth->event($request->user(), $data['event_name'], $data['source'] ?? 'app', $data['meta'] ?? []);
        return response()->json(['ok' => true]);
    }
}
