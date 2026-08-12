{{--
    Full-page create/edit fallback. The index page opens a modal for this
    by default now, but this route (GET .../create, GET .../{id}/edit)
    still works if linked to directly — progressive enhancement, not a
    replacement.
--}}
@extends('layouts.app')

@section('title', ($item->exists ? 'Edit' : 'New') . ' ' . $title)

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-{{ $accent ?? 'indigo' }}-100 text-{{ $accent ?? 'indigo' }}-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="{{ $icon ?? 'fa-solid fa-table-list' }} text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ $item->exists ? 'Edit' : 'New' }} {{ $title }}</h1>
    </div>

    <form method="POST"
          action="{{ $item->exists ? route($routeName . '.update', $item->id) : route($routeName . '.store') }}"
          class="pm-card-bg rounded-xl shadow-sm border border-slate-100 p-6 max-w-xl space-y-5">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif

        @include('crud._fields', ['fields' => $fields, 'item' => $item])

        <div class="flex items-center gap-3 pt-2 border-t border-slate-100">
            <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                <span>Save</span>
            </button>
            <a href="{{ route($routeName . '.index') }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>
@endsection
