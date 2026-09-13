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
        Carbon::setTestNow(Carbon::create(2026, 9, 13, 12, 0, 0, config('app.timezone')));
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

    public function test_meetings_put_current_and_upcoming_before_past_and_expose_nearest(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 13, 12, 0, 0, config('app.timezone')));
        $user = User::factory()->create();

        Meeting::create(['user_id' => $user->id, 'title' => 'Past', 'start_at' => now()->subDay(), 'status' => 'scheduled']);
        $current = Meeting::create(['user_id' => $user->id, 'title' => 'Current', 'start_at' => now()->subMinutes(10), 'end_at' => now()->addMinutes(20), 'status' => 'scheduled']);
        Meeting::create(['user_id' => $user->id, 'title' => 'Upcoming', 'start_at' => now()->addDay(), 'status' => 'scheduled']);

        $request = Request::create(route('meetings.index'), 'GET');
        $request->setUserResolver(fn () => $user);
        $view = app(MeetingController::class)->index($request);
        $items = $view->getData()['items'];

        $this->assertCount(3, $items->getCollection());
        $this->assertSame($current->id, $view->getData()['nearestMeeting']->id);
    }

    public function test_stats_only_count_internal_diary_meetings_and_their_invitees(): void
    {
        $user = User::factory()->create();

        Meeting::create([
            'user_id' => $user->id,
            'title' => 'Upcoming diary meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'one@example.com; two@example.com',
        ]);
        Meeting::create([
            'user_id' => $user->id,
            'title' => 'Completed diary meeting',
            'start_at' => now()->subDay(),
            'status' => 'completed',
            'attendees' => "two@example.com\nthree@example.com",
        ]);
        $external = Meeting::create([
            'user_id' => $user->id,
            'title' => 'Imported meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'external@example.com',
            'external_platform' => 'google',
            'external_id' => 'external-event',
        ]);
        Meeting::create([
            'user_id' => $user->id,
            'copied_from_meeting_id' => $external->id,
            'title' => 'Copied imported meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'copied-external@example.com',
        ]);

        $request = Request::create(route('meetings.index'), 'GET');
        $request->setUserResolver(fn () => $user);
        $stats = app(MeetingController::class)->index($request)->getData()['stats'];

        $this->assertSame([
            'Total' => '2',
            'Upcoming' => '1',
            'Completed' => '1',
            'Invited attendees' => '3',
        ], collect($stats)->pluck('value', 'label')->all());
    }

    public function test_invited_user_can_add_meeting_once_but_owner_and_unauthorized_user_cannot(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $meeting = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Internal planning',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'invitee@example.com',
        ]);

        $ownerRequest = Request::create(route('meetings.add-to-calendar', $meeting), 'POST');
        $ownerRequest->setUserResolver(fn () => $owner);
        app(MeetingController::class)->addToCalendar($ownerRequest, $meeting->id);
        $this->assertSame(1, Meeting::where('user_id', $owner->id)->count());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $request = Request::create(route('meetings.add-to-calendar', $meeting), 'POST');
        $request->setUserResolver(fn () => $stranger);
        app(MeetingController::class)->addToCalendar($request, $meeting->id);
    }

    public function test_invited_user_adds_meeting_and_repeated_add_is_duplicate_safe(): void
    {
        $owner = User::factory()->create(['email' => 'owner2@example.com']);
        $invitee = User::factory()->create(['email' => 'invitee2@example.com']);
        $meeting = Meeting::create([
            'user_id' => $owner->id,
            'title' => 'Internal planning',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
            'attendees' => 'invitee2@example.com',
        ]);

        $request = Request::create(route('meetings.add-to-calendar', $meeting), 'POST');
        $request->setUserResolver(fn () => $invitee);
        app(MeetingController::class)->addToCalendar($request, $meeting->id);
        $this->assertDatabaseHas('meetings', ['user_id' => $invitee->id, 'copied_from_meeting_id' => $meeting->id]);

        app(MeetingController::class)->addToCalendar($request, $meeting->id);
        $this->assertSame(1, Meeting::where('user_id', $invitee->id)->where('copied_from_meeting_id', $meeting->id)->count());
    }

    public function test_invited_visibility_requires_an_exact_normalized_attendee_email(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $invitee = User::factory()->create(['email' => 'attendee@example.com']);

        $exact = Meeting::create([
            'user_id' => $owner->id, 'title' => 'Exact invite', 'start_at' => now()->addDay(),
            'status' => 'scheduled', 'attendees' => ' OTHER@example.com; attendee@example.com ',
        ]);
        Meeting::create([
            'user_id' => $owner->id, 'title' => 'Substring only', 'start_at' => now()->addDay(),
            'status' => 'scheduled', 'attendees' => 'attendee@example.com.evil',
        ]);

        $request = Request::create(route('meetings.index'), 'GET');
        $request->setUserResolver(fn () => $invitee);
        $view = app(MeetingController::class)->index($request);

        $items = $view->getData()['items']->getCollection();
        $this->assertCount(1, $items);
        $this->assertSame($exact->id, $items->first()->id);
        $this->assertSame('1', collect($view->getData()['stats'])->firstWhere('label', 'Total')['value']);
    }
}
