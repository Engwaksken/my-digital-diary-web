@component('mail::message')
# New Enterprise Inquiry

@component('mail::panel')
**Email:** {{ $inquiry->email }}<br>
**Country:** {{ $inquiry->country }}<br>
**Employee Count:** {{ $inquiry->employee_count }}<br>
**About:** {{ $inquiry->about }}
@endcomponent

@component('mail::button', ['url' => route('admin.enterprise-inquiries.index')])
View in Admin
@endcomponent

{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
