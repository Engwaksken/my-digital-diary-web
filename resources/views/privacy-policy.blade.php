@extends('layouts.app')

@section('title', 'Privacy Policy')

@section('content')
    @php $settings = \App\Models\SiteSetting::current(); @endphp
    <div class="max-w-2xl mx-auto pm-card-bg shadow-sm border border-slate-100 rounded-xl p-8">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-file-shield text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Privacy Policy</h1>
        </div>
        <p class="text-sm text-slate-500 mb-6">Version {{ $settings->privacy_policy_version }}</p>

        {{-- Admin-editable content (see Admin -> Settings -> Privacy
             Policy tab), rendered as plain text with white-space:pre-line
             so paragraph breaks are preserved deliberately NOT raw HTML,
             since an admin-editable field rendered unescaped would be a
             stored-XSS risk for every visitor. --}}
        <div class="text-sm text-slate-600 leading-relaxed" style="white-space: pre-line;">{{ $settings->privacyPolicyContent() }}</div>
    </div>
@endsection
