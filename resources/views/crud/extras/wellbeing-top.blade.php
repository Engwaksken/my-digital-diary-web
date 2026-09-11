<div class="mb-5 rounded-xl border border-teal-100 bg-teal-50/60 px-4 py-3">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-semibold text-slate-800">
                <i class="fa-solid fa-calendar-check mr-1.5 text-teal-600"></i>
                Wellbeing check-ins
            </p>
            <p class="mt-1 text-xs leading-5 text-slate-600">
                Choose Once, Daily, Weekly or Monthly when adding a new wellbeing check-in.
                Connected Steps, Diet, Sleep and Exercise still update health statistics automatically in the background.
            </p>
        </div>

        <form method="GET"
              action="{{ route('wellbeing.index') }}"
              class="flex items-center gap-2 shrink-0">
            @foreach(request()->except(['per_page', 'page']) as $key => $value)
                @if(!is_array($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach

            <label for="wellbeing-per-page" class="text-xs font-semibold text-slate-600">
                Records per page
            </label>
            <select id="wellbeing-per-page"
                    name="per_page"
                    class="pm-input py-2 text-sm"
                    onchange="this.form.submit()">
                @foreach([10,25,50] as $size)
                    <option value="{{ $size }}"
                            @selected((int) request('per_page', $perPage ?? 10) === $size)>
                        {{ $size }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>
</div>
