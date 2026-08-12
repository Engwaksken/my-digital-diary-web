<div>
    <label class="text-sm font-medium text-slate-700">Plan / Goal</label>
    <input name="title" required class="pm-input mt-1" placeholder="e.g. Complete professional certification">
</div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <div>
        <label class="text-sm font-medium text-slate-700">Year</label>
        <input type="number" name="plan_year" value="{{ $year }}" min="2000" max="2100" required class="pm-input mt-1">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Target date</label>
        <input type="date" name="target_date" class="pm-input mt-1">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Progress %</label>
        <input type="number" name="progress_percent" value="0" min="0" max="100" required class="pm-input mt-1">
    </div>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Description</label>
    <textarea name="description" rows="4" class="pm-input mt-1" placeholder="What do you want to achieve and how will you measure it?"></textarea>
</div>
