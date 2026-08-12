@component('mail::message')
@if ($siteSettings->logoUrl())
<img src="{{ $siteSettings->logoUrl() }}" alt="{{ $siteSettings->site_name }}" style="height: 40px; margin-bottom: 12px;">
@endif

# You're invited to join {{ $organization->name }}

You've been invited to join **{{ $organization->name }}** on {{ $siteSettings->site_name ?? config('app.name') }} as {{ $member->role === 'admin' ? 'an administrator' : 'a team member' }}.

@component('mail::button', ['url' => route('organization.accept-invite', $member->invite_token)])
Accept Invitation
@endcomponent

This invitation was sent to {{ $member->invited_email }}. If you don't already have an account, you'll be asked to create one first.

Thanks,<br>
{{ $siteSettings->site_name ?? config('app.name') }}
@endcomponent
