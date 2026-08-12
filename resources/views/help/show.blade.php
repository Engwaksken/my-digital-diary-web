@extends('layouts.app')

@section('title', 'Help')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-circle-question text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Help &amp; FAQ</h1>
        </div>

        @foreach ($faqs as $section => $questions)
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">{{ $section }}</h2>
                <div class="space-y-4">
                    @foreach ($questions as $question => $answer)
                        <details class="group border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                            <summary class="cursor-pointer font-medium text-slate-700 flex items-center justify-between">
                                {{ $question }}
                                <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform group-open:rotate-180" aria-hidden="true"></i>
                            </summary>
                            <p class="text-sm text-slate-500 mt-2">{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm text-slate-600">
            Didn't find what you're looking for?
            <a href="{{ route('feedback.index') }}" class="text-[var(--brand-1)] hover:underline font-medium">Send us feedback</a>
            and we'll get back to you.
        </div>
    </div>
@endsection
