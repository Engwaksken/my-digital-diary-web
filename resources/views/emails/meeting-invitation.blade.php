@component('mail::message')
# You're invited: {{ $meeting->title }}

**{{ $organizerName }}** has invited you to a meeting.

@component('mail::panel')
**When:** {{ $meeting->start_at?->format('l, F j, Y \a\t g:i A') ?? 'Not yet set' }}
@if ($meeting->end_at)
**Ends:** {{ $meeting->end_at->format('g:i A') }}
@endif
@if ($meeting->location)
**Location / Link:** {{ $meeting->location }}
@endif
@endcomponent

@if ($meeting->diary_join_url)
@component('mail::button', ['url' => $meeting->diary_join_url])
Join in My Digital Diary
@endcomponent

This link requires you to sign in with the email address that received this invitation.
@endif

@if ($meeting->notes)
{{ $meeting->notes }}
@endif

Thanks,<br>
{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
