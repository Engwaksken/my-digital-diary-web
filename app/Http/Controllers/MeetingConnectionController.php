<?php

namespace App\Http\Controllers;

use App\Models\MeetingPlatformConfig;
use App\Models\Meeting;
use App\Models\UserMeetingConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lets a user connect THEIR OWN account on a meeting platform (Zoom,
 * Google, Microsoft, Webex) via standard OAuth2 — the admin-configured
 * client_id/secret (see MeetingPlatformConfig) identifies THIS APP to the
 * platform; this controller handles one specific user's login/consent
 * and the resulting token exchange.
 *
 * IMPORTANT — this is written against each platform's published, stable
 * OAuth2 + calendar/meetings API endpoints, but has NOT been tested
 * against a real account (this project has no live Zoom/Google/
 * Microsoft/Webex credentials to test with). The OAuth2 flow itself is
 * standardized and should work as written; the exact shape of each
 * platform's "list my events" JSON response is the part most likely to
 * need a small adjustment once tested against a real connected account —
 * see fetchEventsFor()'s per-platform parsing.
 */
class MeetingConnectionController extends Controller
{
    /**
     * @return array<string, array{authorize_url: string, token_url: string, scope: string, events_url: string}>
     */
    private function providerConfig(string $platform): ?array
    {
        return match ($platform) {
            'zoom' => [
                'authorize_url' => 'https://zoom.us/oauth/authorize',
                'token_url' => 'https://zoom.us/oauth/token',
                'scope' => 'meeting:read',
                'events_url' => 'https://api.zoom.us/v2/users/me/meetings?type=upcoming',
            ],
            'google' => [
                'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'scope' => 'https://www.googleapis.com/auth/calendar.readonly',
                'events_url' => 'https://www.googleapis.com/calendar/v3/calendars/primary/events?maxResults=50&orderBy=startTime&singleEvents=true&timeMin=' . now()->subDays(7)->toIso8601String(),
            ],
            'microsoft' => [
                'authorize_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'scope' => 'offline_access Calendars.Read',
                'events_url' => 'https://graph.microsoft.com/v1.0/me/events?$top=50&$orderby=start/dateTime',
            ],
            'webex' => [
                'authorize_url' => 'https://webexapis.com/v1/authorize',
                'token_url' => 'https://webexapis.com/v1/access_token',
                'scope' => 'meeting:schedules_read',
                'events_url' => 'https://webexapis.com/v1/meetings?max=50',
            ],
            default => null,
        };
    }

    public function connect(Request $request, string $platform): RedirectResponse
    {
        $config = MeetingPlatformConfig::where('platform', $platform)->where('is_enabled', true)->first();
        $providerConfig = $this->providerConfig($platform);

        if (! $config || ! $providerConfig) {
            return back()->withErrors(['platform' => 'This platform is not enabled by the administrator.']);
        }

        $state = Str::random(40);
        session(['meeting_oauth_state_' . $platform => $state]);

        $params = [
            'response_type' => 'code',
            'client_id' => $config->client_id,
            'redirect_uri' => $this->redirectUri($platform),
            'scope' => $providerConfig['scope'],
            'state' => $state,
            'access_type' => 'offline', // Google-specific; harmless for other providers
            'prompt' => 'consent',
        ];

        return redirect($providerConfig['authorize_url'] . '?' . http_build_query($params));
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $config = MeetingPlatformConfig::where('platform', $platform)->first();
        $providerConfig = $this->providerConfig($platform);

        if (! $config || ! $providerConfig) {
            return redirect()->route('meetings.index')->withErrors(['platform' => 'Unknown meeting platform.']);
        }

        if ($request->query('state') !== session('meeting_oauth_state_' . $platform)) {
            return redirect()->route('meetings.index')->withErrors(['platform' => 'Could not verify this login attempt — please try connecting again.']);
        }

        if ($request->has('error')) {
            return redirect()->route('meetings.index')->withErrors(['platform' => 'Connection cancelled or denied.']);
        }

        $response = Http::asForm()->post($providerConfig['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => $request->query('code'),
            'redirect_uri' => $this->redirectUri($platform),
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
        ]);

        if (! $response->successful()) {
            return redirect()->route('meetings.index')->withErrors(['platform' => 'Could not complete the connection — the platform rejected the request.']);
        }

        $tokenData = $response->json();

        UserMeetingConnection::updateOrCreate(
            ['user_id' => $request->user()->id, 'platform' => $platform],
            [
                'access_token' => $tokenData['access_token'] ?? null,
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds((int) $tokenData['expires_in']) : null,
                'connected_email' => $request->user()->email,
            ]
        );

        return redirect()->route('meetings.index')->with('success', ucfirst($platform) . ' connected — meetings will sync shortly.');
    }

    public function disconnect(Request $request, string $platform): RedirectResponse
    {
        UserMeetingConnection::where('user_id', $request->user()->id)->where('platform', $platform)->delete();

        return redirect()->route('meetings.index')->with('success', ucfirst($platform) . ' disconnected.');
    }

    /**
     * Fetches events from every platform the user has connected, and
     * upserts them into `meetings` — deduped by (external_platform,
     * external_id), so the SAME event fetched again (including via a
     * second linked email — see the class docblock) never creates a
     * duplicate row.
     */
    public function sync(Request $request): RedirectResponse
    {
        $connections = UserMeetingConnection::where('user_id', $request->user()->id)->get();
        $imported = 0;

        foreach ($connections as $connection) {
            $providerConfig = $this->providerConfig($connection->platform);
            if (! $providerConfig) {
                continue;
            }

            $response = Http::withToken($connection->access_token)->get($providerConfig['events_url']);
            if (! $response->successful()) {
                continue;
            }

            foreach ($this->parseEvents($connection->platform, $response->json()) as $event) {
                Meeting::updateOrCreate(
                    ['external_platform' => $connection->platform, 'external_id' => $event['external_id']],
                    [
                        'user_id' => $request->user()->id,
                        'title' => $event['title'],
                        'start_at' => $event['start_at'],
                        'end_at' => $event['end_at'],
                        'location' => $event['location'],
                        'status' => 'scheduled',
                        'meeting_status' => 'scheduled',
                    ]
                );
                $imported++;
            }

            $connection->update(['last_synced_at' => now()]);
        }

        return redirect()->route('meetings.index')->with('success', "Synced — {$imported} meeting(s) imported/updated.");
    }

    /**
     * Normalizes each platform's very different JSON shape into a
     * common {external_id, title, start_at, end_at, location} array.
     * This is the part most likely to need adjusting once tested against
     * a real connected account — each platform's actual response has
     * been implemented from published API docs, not verified live.
     */
    private function parseEvents(string $platform, array $response): array
    {
        return match ($platform) {
            'zoom' => collect($response['meetings'] ?? [])->map(fn ($m) => [
                'external_id' => (string) $m['id'],
                'title' => $m['topic'] ?? 'Zoom Meeting',
                'start_at' => $m['start_time'] ?? null,
                'end_at' => null,
                'location' => $m['join_url'] ?? null,
            ])->filter(fn ($m) => $m['start_at'])->values()->all(),

            'google' => collect($response['items'] ?? [])->map(fn ($e) => [
                'external_id' => $e['id'],
                'title' => $e['summary'] ?? 'Google Calendar Event',
                'start_at' => $e['start']['dateTime'] ?? $e['start']['date'] ?? null,
                'end_at' => $e['end']['dateTime'] ?? $e['end']['date'] ?? null,
                'location' => $e['hangoutLink'] ?? $e['location'] ?? null,
            ])->filter(fn ($e) => $e['start_at'])->values()->all(),

            'microsoft' => collect($response['value'] ?? [])->map(fn ($e) => [
                'external_id' => $e['id'],
                'title' => $e['subject'] ?? 'Teams Meeting',
                'start_at' => $e['start']['dateTime'] ?? null,
                'end_at' => $e['end']['dateTime'] ?? null,
                'location' => $e['onlineMeeting']['joinUrl'] ?? null,
            ])->filter(fn ($e) => $e['start_at'])->values()->all(),

            'webex' => collect($response['items'] ?? [])->map(fn ($m) => [
                'external_id' => $m['id'],
                'title' => $m['title'] ?? 'Webex Meeting',
                'start_at' => $m['start'] ?? null,
                'end_at' => $m['end'] ?? null,
                'location' => $m['webLink'] ?? null,
            ])->filter(fn ($m) => $m['start_at'])->values()->all(),

            default => [],
        };
    }

    private function redirectUri(string $platform): string
    {
        return url('/meetings/connect/' . $platform . '/callback');
    }
}
