@extends('layouts.app')

@section('title', 'New Announcement')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-bullhorn text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">New Announcement</h1>
    </div>

    <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm mb-6">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        This sends immediately to every user's in-app notifications (web and mobile) — there's no preview
        or undo once submitted.
    </div>

    <form method="POST" action="{{ route('admin.announcements.store') }}" class="max-w-xl pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 space-y-5">
        @csrf

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Title</label>
            <input type="text" id="title" name="title" value="{{ old('title') }}"
                   required aria-required="true" maxlength="255" class="pm-input">
            @error('title')
                <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="body" class="block text-sm font-medium text-slate-700 mb-1">Message</label>
            <textarea id="body" name="body" rows="6" required aria-required="true" maxlength="2000"
                      class="pm-input">{{ old('body') }}</textarea>
            <p class="text-xs text-slate-400 mt-1">Plain text — shown as-is in each user's notification list.</p>
            @error('body')
                <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                Send to Everyone
            </button>
            <a href="{{ route('admin.announcements.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
