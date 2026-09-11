@extends('layouts.admin')

@section('title', 'SMS Providers')

@section('content')
<div class="max-w-6xl mx-auto p-6 space-y-5">
    <div>
        <h1 class="text-2xl font-bold">SMS Providers</h1>
        <p class="text-sm text-slate-500 mt-1">Configure the system SMS API used by debt and savings reminders.</p>
    </div>

    <form method="POST" action="{{ route('admin.sms-providers.store') }}" class="bg-white border rounded-2xl p-5 grid md:grid-cols-2 gap-3">
        @csrf
        <input name="name" class="pm-input" placeholder="Provider name" required>
        <input name="endpoint" class="pm-input" placeholder="https://provider.example/send" required>
        <select name="http_method" class="pm-input"><option>POST</option><option>GET</option></select>
        <select name="auth_type" class="pm-input"><option value="bearer">Bearer token</option><option value="header">X-API-Key</option><option value="basic">Basic</option><option value="none">No auth</option></select>
        <input name="api_key" class="pm-input" placeholder="API key / token">
        <input name="sender_id" class="pm-input" placeholder="Sender ID (optional)">
        <input name="recipient_field" value="to" class="pm-input" placeholder="Recipient payload field">
        <input name="message_field" value="message" class="pm-input" placeholder="Message payload field">
        <input name="sender_field" class="pm-input" placeholder="Sender payload field (optional)">
        <div class="flex gap-4 items-center">
            <label><input type="checkbox" name="is_enabled" value="1"> Enabled</label>
            <label><input type="checkbox" name="is_default" value="1"> Default</label>
        </div>
        <textarea name="extra_headers" class="pm-input md:col-span-1" rows="3" placeholder='Extra headers JSON, e.g. {"Accept":"application/json"}'></textarea>
        <textarea name="extra_payload" class="pm-input md:col-span-1" rows="3" placeholder='Extra payload JSON'></textarea>
        <button class="md:col-span-2 px-4 py-2 rounded-lg bg-slate-900 text-white font-semibold">Save Provider</button>
    </form>

    <div class="space-y-3">
        @forelse($providers as $provider)
            <form method="POST" action="{{ route('admin.sms-providers.update',$provider) }}" class="bg-white border rounded-2xl p-4 grid md:grid-cols-4 gap-3">
                @csrf @method('PUT')
                <input name="name" value="{{ $provider->name }}" class="pm-input" required>
                <input name="endpoint" value="{{ $provider->endpoint }}" class="pm-input md:col-span-2" required>
                <select name="http_method" class="pm-input"><option @selected($provider->http_method==='POST')>POST</option><option @selected($provider->http_method==='GET')>GET</option></select>
                <select name="auth_type" class="pm-input"><option value="bearer" @selected($provider->auth_type==='bearer')>Bearer token</option><option value="header" @selected($provider->auth_type==='header')>X-API-Key</option><option value="basic" @selected($provider->auth_type==='basic')>Basic</option><option value="none" @selected($provider->auth_type==='none')>No auth</option></select>
                <input name="api_key" class="pm-input" placeholder="Leave blank to keep key">
                <input name="sender_id" value="{{ $provider->sender_id }}" class="pm-input">
                <input name="recipient_field" value="{{ $provider->recipient_field }}" class="pm-input" required>
                <input name="message_field" value="{{ $provider->message_field }}" class="pm-input" required>
                <input name="sender_field" value="{{ $provider->sender_field }}" class="pm-input">
                <label><input type="checkbox" name="is_enabled" value="1" @checked($provider->is_enabled)> Enabled</label>
                <label><input type="checkbox" name="is_default" value="1" @checked($provider->is_default)> Default</label>
                <textarea name="extra_headers" class="pm-input md:col-span-2">{{ json_encode($provider->extra_headers ?: new stdClass) }}</textarea>
                <textarea name="extra_payload" class="pm-input md:col-span-2">{{ json_encode($provider->extra_payload ?: new stdClass) }}</textarea>
                <button class="px-3 py-2 bg-slate-900 text-white rounded-lg">Update</button>
            </form>
        @empty
            <div class="bg-white border rounded-xl p-8 text-center text-slate-500">No SMS provider configured.</div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('admin.sms-providers.test') }}" class="bg-white border rounded-2xl p-4 flex flex-col md:flex-row gap-3">
        @csrf
        <input name="phone" class="pm-input flex-1" placeholder="+256..." required>
        <input name="message" class="pm-input flex-[2]" placeholder="Optional test message">
        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold">Send Test SMS</button>
    </form>
</div>
@endsection
