@extends('layouts.app')

@section('title', 'Manage Users')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-users text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Manage Users</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.login-activities.index') }}" class="apple-btn">
                <i class="fa-solid fa-shield-halved text-[var(--brand-1)]"></i> Login Activities
            </a>
            <button type="button" onclick="document.getElementById('user-create-modal').showModal()"
                    class="inline-flex items-center justify-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                <span>Add User</span>
            </button>
        </div>
    </div>

    <p class="text-sm text-slate-500 mb-4 flex items-start gap-2">
        <i class="fa-solid fa-circle-info mt-0.5 text-slate-400" aria-hidden="true"></i>
        <span>
            Account, subscription, and access management only. This screen never shows anyone's
            personal tracked data (plans, expenses, health records, etc.) — see
            <a href="{{ route('admin.statistics') }}" class="text-[var(--brand-1)] hover:underline">Statistics</a>
            for aggregate numbers instead.
        </span>
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 border-l-4 border-l-{{ $stat['color'] }}-400 p-4">
                <div class="w-9 h-9 rounded-lg bg-{{ $stat['color'] }}-50 text-{{ $stat['color'] }}-600 flex items-center justify-center mb-2">
                    <i class="{{ $stat['icon'] }} text-sm" aria-hidden="true"></i>
                </div>
                <p class="text-xs text-slate-500 uppercase tracking-wide truncate">{{ $stat['label'] }}</p>
                <p class="text-xl font-bold text-slate-800 truncate">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex-1 min-w-[180px] max-w-xs">
            <label for="admin-users-q" class="sr-only">Search users</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="admin-users-q" name="q" value="{{ $search }}" placeholder="Search name or email..."
                       class="pm-input pl-9 text-sm">
            </div>
        </div>

        <div>
            <label for="admin-users-period" class="sr-only">Filter by join date</label>
            <select id="admin-users-period" name="period" onchange="pmToggleAdminUsersDateRange(this)" class="pm-input text-sm">
                <option value="" @selected(!$period)>All time</option>
                <option value="daily" @selected($period === 'daily')>Joined today</option>
                <option value="weekly" @selected($period === 'weekly')>Joined this week</option>
                <option value="monthly" @selected($period === 'monthly')>Joined this month</option>
                <option value="range" @selected($period === 'range')>Custom range...</option>
            </select>
        </div>

        <div id="admin-users-date-range" class="flex items-end gap-2" style="{{ $period === 'range' ? '' : 'display: none;' }}">
            <input type="date" name="from" value="{{ $from }}" class="pm-input text-sm">
            <span class="text-slate-400 text-sm pb-2">to</span>
            <input type="date" name="to" value="{{ $to }}" class="pm-input text-sm">
        </div>

        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
            Filter
        </button>
        @if ($search || $period)
            <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors pb-2.5">Clear</a>
        @endif
    </form>

    <script>
        function pmToggleAdminUsersDateRange(select) {
            var wrapper = document.getElementById('admin-users-date-range');
            if (wrapper) { wrapper.style.display = select.value === 'range' ? 'flex' : 'none'; }
        }
    </script>

    <form method="POST" id="admin-users-bulk-form" action="{{ route('admin.users.bulk') }}">
        @csrf
        <div id="admin-users-bulk-bar" class="hidden items-center justify-between pm-card-bg border border-amber-200 bg-amber-50 rounded-lg px-4 py-3 mb-3">
            <span class="text-sm text-amber-800"><span id="admin-users-selected-count">0</span> selected</span>
            <div class="flex items-center gap-3">
                <button type="submit" name="action" value="suspend" class="text-sm text-amber-800 hover:underline"
                        data-confirm-click="Suspend all selected users?" data-confirm-title="Suspend selected users?" data-confirm-text="Suspend">Suspend</button>
                <button type="submit" name="action" value="unsuspend" class="text-sm text-emerald-700 hover:underline">Reactivate</button>
                <button type="submit" name="action" value="delete" class="text-sm text-rose-600 hover:underline"
                        data-confirm-click="Permanently delete all selected users and their data? This cannot be undone." data-confirm-title="Delete selected users?" data-confirm-text="Delete permanently">Delete</button>
            </div>
        </div>

        <div class="pm-card-bg rounded-xl shadow-sm border border-slate-100 overflow-x-auto pm-admin-table-scroll" role="region" aria-label="Users table" tabindex="0">
        <table class="min-w-full text-sm pm-admin-horizontal-table">
            <caption class="sr-only">All registered users, with role, subscription status, usage progress, and a link to manage each.</caption>
            <thead class="bg-slate-50 text-left border-b border-slate-100">
                <tr>
                    <th scope="col" class="px-4 py-3 w-8">
                        <input type="checkbox" id="admin-users-select-all" onchange="pmToggleAllUserCheckboxes(this)"
                               class="rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]" aria-label="Select all users">
                    </th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Name</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Email</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Role</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Status</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide min-w-[150px]">Usage</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-slate-500 text-xs uppercase tracking-wide">Joined</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3">
                            @if ($user->id !== auth()->id())
                                <input type="checkbox" name="user_ids[]" value="{{ $user->id }}"
                                       class="admin-user-checkbox rounded border-slate-300 text-[var(--brand-1)] focus:ring-[var(--brand-2)]"
                                       onchange="pmUpdateUserBulkBar()" aria-label="Select {{ $user->name }}">
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $user->name }}
                            @if ($user->id === auth()->id())
                                <span class="text-xs text-slate-400">(you)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($user->isAdmin())
                                <span class="inline-flex items-center gap-1 text-[var(--brand-1)] font-medium">
                                    <i class="fa-solid fa-shield text-xs" aria-hidden="true"></i> Admin
                                </span>
                            @else
                                <span class="text-slate-600">User</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->isSuspended())
                                <span class="inline-flex items-center gap-1 text-rose-600 font-medium">
                                    <i class="fa-solid fa-ban text-xs" aria-hidden="true"></i> Suspended
                                </span>
                            @else
                                <span class="text-slate-600">{{ ucfirst($user->subscription_status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php $usagePercent = (int) ($usageByUser[$user->id] ?? 0); @endphp
                            <div class="flex items-center gap-2 min-w-[130px]">
                                <div class="h-2 flex-1 rounded-full bg-slate-100 overflow-hidden" aria-hidden="true">
                                    <div class="h-full rounded-full bg-[var(--brand-1)] transition-all duration-500" style="width: {{ $usagePercent }}%"></div>
                                </div>
                                <span class="text-xs font-bold text-slate-600 w-9 text-right">{{ $usagePercent }}%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">App usage progress</p>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.users.show', $user->id) }}" class="inline-flex items-center gap-1 text-[var(--brand-1)] hover:text-[var(--brand-1-dark)] transition-colors">
                                <i class="fa-solid fa-gear text-xs" aria-hidden="true"></i>
                                Manage<span class="sr-only"> {{ $user->name }}</span>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <nav aria-label="Pagination" class="mt-4">
            {{ $users->links() }}
        </nav>
    </form>

    <script>
        function pmUpdateUserBulkBar() {
            var checked = document.querySelectorAll('.admin-user-checkbox:checked').length;
            var bar = document.getElementById('admin-users-bulk-bar');
            document.getElementById('admin-users-selected-count').textContent = checked;
            bar.classList.toggle('hidden', checked === 0);
            bar.classList.toggle('flex', checked > 0);
        }

        function pmToggleAllUserCheckboxes(selectAllEl) {
            document.querySelectorAll('.admin-user-checkbox').forEach(function (cb) {
                cb.checked = selectAllEl.checked;
            });
            pmUpdateUserBulkBar();
        }
    </script>

    {{-- Add User modal — replaces the old full-page /create navigation.
         The dedicated route/view still exist for direct-URL access, same
         progressive-enhancement pattern as every other module. --}}
    <dialog id="user-create-modal" aria-labelledby="user-create-modal-title" class="rounded-2xl p-0 pm-dialog-lg shadow-2xl backdrop:bg-slate-900/50">
        <form method="POST" action="{{ route('admin.users.store') }}" class="p-6 space-y-5">
            @csrf

            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[var(--brand-1-tint-10)] text-[var(--brand-1)] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-user-plus text-sm" aria-hidden="true"></i>
                    </div>
                    <h2 id="user-create-modal-title" class="text-lg font-bold text-slate-800">Add User</h2>
                </div>
                <button type="button" onclick="document.getElementById('user-create-modal').close()"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

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
                <select id="role" name="role" class="pm-input">
                    <option value="user" @selected(old('role', 'user') === 'user')>User</option>
                    <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                </select>
            </div>

            <p class="text-xs text-slate-400">
                Note: this account starts with no data-processing consent on record — that has to come
                from the person themselves, not be granted on their behalf. They'll see this on their
                own Privacy &amp; Data page.
            </p>

            <div class="flex items-center gap-3 pt-2 border-t border-slate-100 mt-2">
                <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <span>Create User</span>
                </button>
                <button type="button" onclick="document.getElementById('user-create-modal').close()" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </dialog>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if ($errors->any() && (old('name') !== null || old('email') !== null))
                document.getElementById('user-create-modal').showModal();
            @endif
        });
    </script>
@endsection
