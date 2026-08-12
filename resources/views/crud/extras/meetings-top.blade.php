{{--
    Status filter buttons + "Connected Accounts" panel for the Meetings
    page — injected via crud/index.blade.php's '-top' extension point.
    $connections/$enabledPlatforms/$statusFilter come from
    MeetingController::index()'s renderIndex() call.
--}}
<div class="flex flex-wrap gap-2 mb-4">
    @foreach (['' => 'All', 'scheduled' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'missed' => 'Missed'] as $value => $label)
        <a href="{{ route('meetings.index', array_merge(request()->except('status_filter', 'page'), $value ? ['status_filter' => $value] : [])) }}"
           class="px-3 py-1.5 rounded-full text-sm font-medium transition-colors {{ (string) $statusFilter === $value ? 'bg-[var(--brand-1)] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

@if ($enabledPlatforms->isNotEmpty())
    <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-link" aria-hidden="true"></i> Connected Accounts
            </h2>
            @if ($connections->isNotEmpty())
                <form method="POST" action="{{ route('meetings.sync') }}">
                    @csrf
                    <button type="submit" class="text-xs text-[var(--brand-1)] hover:underline">
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i> Sync now
                    </button>
                </form>
            @endif
        </div>
        <div class="flex flex-wrap gap-3">
            @foreach ($enabledPlatforms as $platform)
                @php $connection = $connections->get($platform->platform); @endphp
                <div class="flex items-center gap-2 border border-slate-200 rounded-lg px-3 py-2">
                    <span class="text-sm text-slate-700">{{ $platform->name }}</span>
                    @if ($connection)
                        <span class="text-xs text-emerald-600"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Connected</span>
                        <form method="POST" action="{{ route('meetings.disconnect', $platform->platform) }}" onsubmit="return confirm('Disconnect {{ $platform->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-rose-500 hover:underline">Disconnect</button>
                        </form>
                    @else
                        <a href="{{ route('meetings.connect', $platform->platform) }}" class="text-xs text-[var(--brand-1)] hover:underline">Connect</a>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($connections->isEmpty())
            <p class="text-xs text-slate-400 mt-2">Connect an account to automatically pull in your meetings from that platform.</p>
        @endif
    </div>
@endif
