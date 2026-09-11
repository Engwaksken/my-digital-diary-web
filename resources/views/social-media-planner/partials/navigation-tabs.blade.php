<div class="smp-main-tabs" role="navigation" aria-label="Social Media Planner">
    <a href="{{ route('social-media-planner.index') }}"
       class="smp-main-tab {{ request()->routeIs('social-media-planner.index') ? 'is-active' : '' }}">
        <i class="fa-solid fa-calendar-days"></i><span>Planner</span>
    </a>

    @if(Route::has('profile.social-media'))
        <a href="{{ route('profile.social-media') }}"
           class="smp-main-tab {{ request()->routeIs('profile.social-media*') ? 'is-active' : '' }}">
            <i class="fa-solid fa-user-gear"></i><span>Accounts</span>
        </a>
    @endif

    @if(Route::has('social-media-planner.reports.index'))
        <a href="{{ route('social-media-planner.reports.index') }}"
           class="smp-main-tab {{ request()->routeIs('social-media-planner.reports.*') || request()->routeIs('social-media-planner.analytics.*') ? 'is-active' : '' }}">
            <i class="fa-solid fa-chart-line"></i><span>Reports</span>
        </a>
    @endif
</div>

@once
<style>
.smp-main-tabs{display:flex;align-items:center;gap:.35rem;width:100%;overflow-x:auto;padding:.35rem;border:1px solid rgb(226 232 240);border-radius:14px;background:rgb(248 250 252);scrollbar-width:none}
.smp-main-tabs::-webkit-scrollbar,.smp-subtabs::-webkit-scrollbar{display:none}
.smp-main-tab{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-width:max-content;padding:.7rem 1rem;border-radius:10px;color:rgb(71 85 105);font-size:.82rem;font-weight:800;text-decoration:none!important;transition:.18s ease}
.smp-main-tab:hover{background:#fff;color:rgb(15 118 110)}
.smp-main-tab.is-active{background:#fff;color:rgb(15 118 110);box-shadow:0 1px 3px rgba(15,23,42,.08)}
.smp-subtabs{display:flex;align-items:center;gap:.25rem;overflow-x:auto;padding-bottom:.15rem;border-bottom:1px solid rgb(226 232 240);scrollbar-width:none}
.smp-subtab{position:relative;display:inline-flex;align-items:center;gap:.4rem;min-width:max-content;padding:.75rem .9rem;border:0;background:transparent;color:rgb(100 116 139);font-size:.8rem;font-weight:800;cursor:pointer}
.smp-subtab::after{content:'';position:absolute;left:.8rem;right:.8rem;bottom:-1px;height:2px;border-radius:999px;background:transparent}
.smp-subtab.is-active{color:rgb(15 118 110)}
.smp-subtab.is-active::after{background:rgb(13 148 136)}
.smp-tab-panel[hidden]{display:none!important}
@media(max-width:640px){.smp-main-tab{flex:1 0 auto;padding:.65rem .8rem}}
</style>
@endonce
