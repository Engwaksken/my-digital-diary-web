@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-12 h-12 rounded-xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-user-plus text-xl" aria-hidden="true"></i>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Add User</h1>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}"
          class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 max-w-lg space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                   required aria-required="true"
                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                   class="pm-input">
            @error('name')
                <p id="name-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   required aria-required="true"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                   class="pm-input">
            @error('email')
                <p id="email-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Temporary Password</label>
            <input type="password" id="password" name="password"
                   required aria-required="true" autocomplete="new-password"
                   aria-describedby="password-hint @error('password') password-error @enderror"
                   @error('password') aria-invalid="true" @enderror
                   class="pm-input">
            <p id="password-hint" class="text-xs text-slate-400 mt-1">
                Share this with the user directly — consider asking them to change it after their first login.
            </p>
            @error('password')
                <p id="password-error" role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="role" class="block text-sm font-medium text-slate-700 mb-1">Role</label>
            <select id="role" name="role"
                    class="pm-input">
                <option value="user" @selected(old('role', 'user') === 'user')>User</option>
                <option value="admin" @selected(old('role') === 'admin')>Admin</option>
            </select>
        </div>

        <p class="text-xs text-slate-400">
            Note: this account starts with no data-processing consent on record — that has to come
            from the person themselves, not be granted on their behalf. They'll see this on their
            own Privacy &amp; Data page.
        </p>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                Create User
            </button>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
