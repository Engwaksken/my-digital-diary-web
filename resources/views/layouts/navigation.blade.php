<nav x-data="{ open: false }" class="pm-topnav" aria-label="Primary navigation">
    <style>
        .pm-topnav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.92); border-bottom: 1px solid rgba(15,23,42,.08); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .pm-topnav-inner { max-width: 80rem; margin: 0 auto; padding: 0 1rem; }
        .pm-topnav-row { min-height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .pm-topnav-left, .pm-topnav-user { display: flex; align-items: center; min-width: 0; }
        .pm-topnav-logo { display: inline-flex; align-items: center; text-decoration: none; }
        .pm-topnav-links { display: none; align-items: stretch; margin-left: 30px; min-height: 68px; }
        .pm-user-trigger { display: inline-flex; align-items: center; gap: 10px; min-height: 42px; padding: 7px 12px; border: 1px solid rgba(15,23,42,.08); border-radius: 13px; background: #fff; color: #475467; font-size: .86rem; font-weight: 600; box-shadow: 0 4px 14px rgba(15,23,42,.04); transition: .18s ease; }
        .pm-user-trigger:hover { border-color: rgba(0,137,123,.25); color: #172033; box-shadow: 0 8px 20px rgba(15,23,42,.07); }
        .pm-mobile-toggle { display: inline-grid; place-items: center; width: 42px; height: 42px; border-radius: 12px; border: 1px solid rgba(15,23,42,.08); background: #fff; color: #475467; }
        .pm-mobile-panel { border-top: 1px solid rgba(15,23,42,.07); background: rgba(255,255,255,.98); box-shadow: 0 18px 28px rgba(15,23,42,.06); }
        .pm-mobile-profile { padding: 16px; border-top: 1px solid rgba(15,23,42,.07); }
        .pm-mobile-name { color: #172033; font-weight: 700; }
        .pm-mobile-email { margin-top: 2px; color: #7a8696; font-size: .78rem; overflow-wrap: anywhere; }
        @media (min-width: 640px) {
            .pm-topnav-inner { padding: 0 1.5rem; }
            .pm-topnav-links { display: flex; }
            .pm-topnav-user { display: flex; }
            .pm-mobile-toggle, .pm-mobile-panel { display: none !important; }
        }
        @media (max-width: 639.98px) { .pm-topnav-user { display: none; } }
    </style>

    <div class="pm-topnav-inner">
        <div class="pm-topnav-row">
            <div class="pm-topnav-left">
                <a href="{{ route('dashboard') }}" class="pm-topnav-logo" aria-label="Dashboard">
                    <x-application-logo class="block h-9 w-auto fill-current text-[var(--brand-1)]" />
                </a>

                <div class="pm-topnav-links">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div>
            </div>

            <div class="pm-topnav-user">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="pm-user-trigger">
                            <span class="truncate max-w-[180px]">{{ Auth::user()->name }}</span>
                            <i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <button type="button" @click="open = ! open" class="pm-mobile-toggle" :aria-expanded="open.toString()" aria-controls="mobile-navigation">
                <span class="sr-only">Toggle navigation</span>
                <i class="fa-solid" :class="open ? 'fa-xmark' : 'fa-bars'" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div id="mobile-navigation" x-cloak x-show="open" x-transition class="pm-mobile-panel sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
        </div>

        <div class="pm-mobile-profile">
            <div class="pm-mobile-name">{{ Auth::user()->name }}</div>
            <div class="pm-mobile-email">{{ Auth::user()->email }}</div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
