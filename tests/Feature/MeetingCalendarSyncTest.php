<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\User;
use App\Models\UserMeetingConnection;
use App\Http\Controllers\MeetingController;
use App\Services\ExternalCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class MeetingCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_excludes_prior_month_and_includes_current_and_future_events(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 13, 12));
        $user = User::factory()->create();
        $connection = UserMeetingConnection::create([
            'user_id' => $user->id,
            'platform' => 'google',
            'access_token' => 'token',
            'token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => [['id' => 'primary']]]),
            'https://www.googleapis.com/calendar/v3/calendars/*/events*' => Http::response([
                'items' => [
                    ['id' => 'old', 'summary' => 'Old', 'start' => ['dateTime' => '2026-08-31T09:00:00Z']],
                    ['id' => 'current', 'summary' => 'Current', 'start' => ['dateTime' => '2026-09-01T09:00:00Z']],
                    ['id' => 'future', 'summary' => 'Future', 'start' => ['dateTime' => '2026-10-01T09:00:00Z']],
                ],
            ]),
        ]);

        app(ExternalCalendarSyncService::class)->syncConnection(
            $connection,
            Carbon::create(2026, 8, 1),
            Carbon::create(2026, 10, 31),
        );

        $this->assertDatabaseMissing('meetings', ['external_id' => 'primary:old']);
        $this->assertDatabaseHas('meetings', ['external_id' => 'primary:current']);
        $this->assertDatabaseHas('meetings', ['external_id' => 'primary:future']);
    }

    public function test_user_can_bulk_delete_more_than_ten_meetings(): void
    {
        $user = User::factory()->create();
        $meetings = collect(range(1, 11))->map(fn () => Meeting::create([
            'user_id' => $user->id,
            'title' => 'Meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
        ]));

        $request = Request::create(route('meetings.bulk-destroy'), 'DELETE', [
            'ids' => $meetings->pluck('id')->all(),
        ]);
        $request->setUserResolver(fn () => $user);

        app(MeetingController::class)->bulkDestroy($request);
        $this->assertDatabaseCount('meetings', 0);
    }
}
