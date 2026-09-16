@component('mail::message')
# {{ $replySubject }}

@component('mail::panel')
{!! nl2br(e($replyBody)) !!}
@endcomponent

@component('mail::button', ['url' => 'mailto:' . $inquiry->email])
Reply via Email
@endcomponent

{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
