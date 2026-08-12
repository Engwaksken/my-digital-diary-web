<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingPlatformConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets an admin register THIS APP as an "OAuth app" on each meeting
 * platform's own developer console, then paste the resulting client
 * ID/secret here. This alone doesn't fetch anyone's meetings — it's what
 * lets a USER then connect their own account (see
 * MeetingConnectionController) to fetch theirs. The four platform rows
 * are seeded once by migration; this only ever updates them, never
 * creates/deletes.
 */
class AdminMeetingPlatformController extends Controller
{
    public function update(Request $request, MeetingPlatformConfig $meetingPlatformConfig): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'string'],
            'client_secret' => ['nullable', 'string'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['client_id'])) {
            $meetingPlatformConfig->client_id = $data['client_id'];
        }

        if (! empty($data['client_secret'])) {
            $meetingPlatformConfig->client_secret = $data['client_secret'];
        }

        $meetingPlatformConfig->is_enabled = $request->boolean('is_enabled') && $meetingPlatformConfig->isConfigured();
        $meetingPlatformConfig->save();

        return back()->with('success', "{$meetingPlatformConfig->name} settings updated.");
    }
}
