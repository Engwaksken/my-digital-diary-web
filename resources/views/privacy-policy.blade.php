@extends('layouts.app')

@section('title', 'Privacy Policy')

@section('content')
    @php
        $settings = \App\Models\SiteSetting::current();
        $logoUrl = $settings->logoUrl();
    @endphp
    <div class="pm-legal-document max-w-3xl mx-auto pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 sm:p-8">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="" aria-hidden="true" class="pm-legal-watermark">
        @endif

        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-6">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $settings->site_name }} logo" class="pm-legal-header-logo">
                @else
                    <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
                        <i class="fa-solid fa-file-shield text-xl" aria-hidden="true"></i>
                    </div>
                @endif
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Privacy Policy</h1>
            </div>
            <p class="text-sm text-slate-500 mb-6">Version {{ $settings->privacy_policy_version ?: '1.0' }}</p>

            <div class="pm-legal-content text-sm text-slate-600 leading-relaxed">{!! $settings->privacyPolicyHtml() !!}</div>
        </div>
    </div>
@endsection
