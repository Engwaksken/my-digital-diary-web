@component('mail::message')
# Verify Your Email

Enter this code to confirm this is your email address:

@component('mail::panel')
# {{ $code }}
@endcomponent

This code expires in 10 minutes.

Thanks,<br>
{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
