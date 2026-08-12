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

@if ($meeting->notes)
{{ $meeting->notes }}
@endif

Thanks,<br>
{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
