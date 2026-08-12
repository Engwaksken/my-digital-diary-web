@extends('layouts.app')

@section('title', 'Annual Plans')

@section('content')
<div class="flex flex-col gap-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-bullseye text-[var(--brand-1)]"></i> Annual Plans
            </h1>
            <p class="text-sm text-slate-500 mt-1">Set your goals for the year, update progress, and tick them off when completed.</p>
        </div>
        <button type="button" onclick="document.getElementById('addAnnualPlan').showModal()" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium">
            <i class="fa-solid fa-plus mr-1"></i> Add Annual Plan
        </button>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="pm-card-bg rounded-xl border border-slate-100 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Plans for {{ $year }}</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $total }}</p>
        </div>
        <div class="pm-card-bg rounded-xl border border-slate-100 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Completed</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $completed }}</p>
        </div>
        <div class="pm-card-bg rounded-xl border border-slate-100 p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Overall Progress</p>
                <span class="text-lg font-bold text-[var(--brand-1)]">{{ $overallProgress }}%</span>
            </div>
            <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden mt-3">
                <div class="h-full bg-[var(--brand-1)] rounded-full" style="width: {{ $overallProgress }}%"></div>
            </div>
        </div>
    </div>

    <form method="GET" class="pm-card-bg rounded-xl border border-slate-100 p-4 flex flex-col sm:flex-row gap-3 sm:items-end">
        <div class="flex-1">
            <label class="text-xs font-medium text-slate-600">Search</label>
            <input type="search" name="q" value="{{ $search }}" class="pm-input mt-1" placeholder="Search annual plans...">
        </div>
        <div class="sm:w-40">
            <label class="text-xs font-medium text-slate-600">Year</label>
            <select name="year" class="pm-input mt-1" onchange="this.form.submit()">
                @foreach($availableYears as $availableYear)
                    <option value="{{ $availableYear }}" @selected((int)$availableYear === $year)>{{ $availableYear }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm">Filter</button>
    </form>

    <form id="bulkAnnualPlansForm" method="POST" action="{{ route('annual-plans.bulk-destroy') }}" onsubmit="return confirm('Delete the selected annual plans?')">
        @csrf @method('DELETE')
    </form>
    <div class="pm-card-bg rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" id="selectAllAnnualPlans" class="rounded"> Select all on this page
            </label>
            <button type="submit" form="bulkAnnualPlansForm" class="text-sm text-rose-600 hover:text-rose-700"><i class="fa-solid fa-trash mr-1"></i> Delete selected</button>
        </div>

            @forelse($plans as $plan)
                <div class="p-4 border-b border-slate-100 last:border-b-0 flex flex-col lg:flex-row gap-4 lg:items-center">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <input type="checkbox" form="bulkAnnualPlansForm" name="ids[]" value="{{ $plan->id }}" class="annual-row-check mt-1 rounded">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-800 {{ $plan->status === 'completed' ? 'line-through text-slate-400' : '' }}">{{ $plan->title }}</h3>
                                @if($plan->status === 'completed')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Completed</span>
                                @endif
                            </div>
                            @if($plan->description)<p class="text-sm text-slate-500 mt-1">{{ $plan->description }}</p>@endif
                            @if($plan->target_date)<p class="text-xs text-slate-400 mt-1">Target: {{ $plan->target_date->format('d M Y') }}</p>@endif
                            <div class="flex items-center gap-3 mt-3">
                                <div class="h-2 bg-slate-100 rounded-full overflow-hidden flex-1 max-w-md">
                                    <div class="h-full bg-[var(--brand-1)]" style="width: {{ $plan->progress_percent }}%"></div>
                                </div>
                                <span class="text-sm font-semibold text-slate-700">{{ $plan->progress_percent }}%</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pl-7 lg:pl-0">
                        <form method="POST" action="{{ route('annual-plans.toggle', $plan) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="completed" value="{{ $plan->status === 'completed' ? 0 : 1 }}">
                            <button class="px-3 py-2 rounded-lg text-sm border {{ $plan->status === 'completed' ? 'border-slate-200 text-slate-600' : 'border-emerald-200 text-emerald-700 bg-emerald-50' }}">
                                <i class="fa-solid {{ $plan->status === 'completed' ? 'fa-rotate-left' : 'fa-square-check' }} mr-1"></i>
                                {{ $plan->status === 'completed' ? 'Reopen' : 'Complete' }}
                            </button>
                        </form>
                        <button type="button" onclick='openAnnualEdit(@json($plan))' class="px-3 py-2 rounded-lg text-sm border border-slate-200 text-slate-600"><i class="fa-solid fa-pen mr-1"></i>Edit</button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-slate-400">No annual plans found for {{ $year }}.</div>
            @endforelse
    </div>

    <div>{{ $plans->links() }}</div>
</div>

<dialog id="addAnnualPlan" class="rounded-2xl p-0 w-[94vw] max-w-xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('annual-plans.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-center justify-between"><h2 class="text-lg font-bold">Add Annual Plan</h2><button type="button" onclick="this.closest('dialog').close()"><i class="fa-solid fa-xmark"></i></button></div>
        @include('annual-plans.partials.form-fields', ['plan' => null, 'year' => $year])
        <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 text-sm">Cancel</button><button class="btn-primary text-white px-4 py-2 rounded-lg text-sm">Save Plan</button></div>
    </form>
</dialog>

<dialog id="editAnnualPlan" class="rounded-2xl p-0 w-[94vw] max-w-xl backdrop:bg-slate-900/40">
    <form id="editAnnualPlanForm" method="POST" class="p-6 space-y-4">
        @csrf @method('PUT')
        <div class="flex items-center justify-between"><h2 class="text-lg font-bold">Edit Annual Plan</h2><button type="button" onclick="this.closest('dialog').close()"><i class="fa-solid fa-xmark"></i></button></div>
        @include('annual-plans.partials.form-fields', ['plan' => null, 'year' => $year, 'editMode' => true])
        <div class="flex justify-end gap-2"><button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2 text-sm">Cancel</button><button class="btn-primary text-white px-4 py-2 rounded-lg text-sm">Update Plan</button></div>
    </form>
</dialog>

<script>
document.getElementById('selectAllAnnualPlans')?.addEventListener('change', function () {
    document.querySelectorAll('.annual-row-check').forEach(cb => cb.checked = this.checked);
});
function openAnnualEdit(plan) {
    const form = document.getElementById('editAnnualPlanForm');
    form.action = '{{ url('annual-plans') }}/' + plan.id;
    form.querySelector('[name=title]').value = plan.title || '';
    form.querySelector('[name=plan_year]').value = plan.plan_year || {{ $year }};
    form.querySelector('[name=target_date]').value = plan.target_date ? String(plan.target_date).slice(0, 10) : '';
    form.querySelector('[name=progress_percent]').value = plan.progress_percent ?? 0;
    form.querySelector('[name=description]').value = plan.description || '';
    document.getElementById('editAnnualPlan').showModal();
}
</script>
@endsection
