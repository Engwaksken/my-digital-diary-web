{{-- Extra header button for the Meetings index page (see crud/index.blade.php's
     view()->exists() extension point). --}}
<button type="button" onclick="document.getElementById('meeting-multi-modal').showModal()"
        class="inline-flex items-center justify-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
    <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
    <span>Schedule Multiple</span>
</button>
