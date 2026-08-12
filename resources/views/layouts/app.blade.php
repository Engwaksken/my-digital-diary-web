<!DOCTYPE html>
<html lang="en">
@php
    // Falls back gracefully if AppServiceProvider hasn't been replaced yet
    // (see README) — the app still renders with defaults instead of a hard
    // "Undefined variable $siteSettings" error on every single page.
    $siteSettings = $siteSettings ?? new \App\Models\SiteSetting(['site_name' => 'Personal Monitor']);

    // Personal accent color (see User::themeColor() and friends) — falls
    // back to the site default forest/sage palette for guests, or if the
    // logged-in user hasn't picked a custom color.
    $themeUser = auth()->user();
@endphp
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $siteSettings->site_name ?? 'Personal Monitor')</title>
    @if (isset($siteSettings) && $siteSettings->faviconUrl())
        <link rel="icon" href="{{ $siteSettings->faviconUrl() }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,400;0,700;1,400&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --brand-1: {{ $themeUser ? $themeUser->themeColor() : '#00897B' }};
            --brand-2: {{ $themeUser ? $themeUser->themeColorLight() : '#73BEB6' }};
            --brand-1-dark: {{ $themeUser ? $themeUser->themeColorDark() : '#006B60' }};
            --brand-2-dark: {{ $themeUser ? $themeUser->themeColorLightDark() : '#62A29B' }};
            --brand-1-tint-10: {{ $themeUser ? $themeUser->themeColorTint(0.1) : 'rgba(0, 137, 123, 0.1)' }};
            --brand-1-tint-20: {{ $themeUser ? $themeUser->themeColorTint(0.2) : 'rgba(0, 137, 123, 0.2)' }};
            --brand-2-tint-25: {{ $themeUser ? $themeUser->themeColorLightTint(0.25) : 'rgba(115, 190, 182, 0.25)' }};
        }
        body { font-family: 'Lato', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .sr-only-focusable:not(:focus) {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
        }
        a:focus-visible, button:focus-visible, input:focus-visible,
        select:focus-visible, textarea:focus-visible {
            outline: 3px solid #FFBA00;
            outline-offset: 2px;
        }
        /* Thin, unobtrusive scrollbar for the sidebar nav on desktop */
        #sidebar nav::-webkit-scrollbar { width: 6px; }
        #sidebar nav::-webkit-scrollbar-thumb { background-color: rgba(148, 163, 184, 0.3); border-radius: 3px; }

        /* Plain CSS rather than Tailwind's bracket-arbitrary-value gradient
           classes (bg-gradient-to-r from-[var(--brand-1)] to-[var(--brand-2)]
           hover:from-[var(--brand-1-dark)] hover:to-[var(--brand-2-dark)]) —
           on request, every primary button app-wide is now a SOLID color,
           not a gradient, and this is the same reliable-regardless-of-
           Tailwind-JIT approach already used for the login/register/verify
           buttons. See README's "All buttons: solid, not gradient" section.
           Hover swaps to the SECONDARY brand color outright (not a darker
           shade of the same primary color) — also on request. */
        .btn-primary {
            background-color: var(--brand-1);
        }
        .btn-primary:hover {
            background-color: var(--brand-2);
        }

        /* Plain CSS rather than Tailwind's border-slate-300/shadow-sm
           utilities for the same reliability reason — form inputs were
           reported as rendering with no visible border/background at all
           on some pages. A hand-written rule with an explicit border color,
           background, and padding can't silently fail to apply the way a
           utility class theoretically could if the CDN's runtime JIT
           scanner didn't pick it up for any reason. */
        .pm-input {
            display: block;
            width: 100%;
            border: 1px solid #94a3b8;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            background-color: #ffffff;
            color: #1e293b;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .pm-input:focus {
            outline: none;
            border-color: var(--brand-2);
            box-shadow: 0 0 0 3px var(--brand-2-tint-25);
        }

        /* Lighter secondary (sage)-tinted PAGE BACKGROUND — cards/tables
           sit on top of it in plain white, so the tint shows in the gaps
           and margins around them rather than tinting the cards
           themselves. (An earlier iteration did the reverse — white page,
           tinted cards — this swaps it per request.) Plain CSS for the
           same reliability reason as .pm-input/.btn-primary above. */
        .pm-card-bg {
            background-color: #ffffff;
        }

        /* Plain CSS rather than Tailwind's w-[92vw]/w-[88vw] bracket-
           arbitrary-value classes for every <dialog>'s mobile width — the
           same bracket-syntax reliability issue that's hit us before
           (buttons/backgrounds not rendering) very likely explains dialogs
           overflowing off the right edge of the screen on mobile: if
           w-[92vw] silently failed to apply, a dialog would fall through
           to JUST its max-w-* class (e.g. 36rem/576px for max-w-xl), which
           is far wider than a ~360-400px phone screen with no responsive
           mobile override at all. `width: min(92vw, Nrem)` here achieves
           the exact same "92% of viewport, capped at N on larger screens"
           behavior as the old two-class combo, but as one guaranteed-to-
           apply rule. */
        .pm-dialog-sm { width: min(88vw, 24rem); }
        .pm-dialog { width: min(92vw, 36rem); }
        .pm-dialog-lg { width: min(92vw, 32rem); }
        .pm-dialog-xl { width: min(92vw, 42rem); }

        /* A visibly SMALLER, lighter treatment than the standard
           .pm-dialog — used specifically when the create modal is opened
           from a calendar day click, to feel closer to the compact
           "quick add event" popup Google Calendar/Teams show, rather than
           a full-page-feeling form dialog. Same fields, same form —
           purely a sizing/spacing difference. */
        .pm-dialog-quick { width: min(90vw, 26rem); }
        .pm-dialog-quick .pm-quick-compact { padding: 1.25rem; }
    </style>
</head>
<body class="text-slate-800" style="background-color: #f3f7f4;">

@include('partials.accessibility-widget')
@include('partials.password-toggle')

<a href="#main-content" class="sr-only-focusable bg-[var(--brand-1)] text-white px-4 py-2 rounded-md z-50 fixed top-2 left-2">
    Skip to main content
</a>

@auth
    @if (auth()->user()->isSuspended())
        <div role="alert" class="bg-rose-100 text-rose-800 text-sm text-center py-2 flex items-center justify-center gap-2">
            <i class="fa-solid fa-ban" aria-hidden="true"></i>
            Your account has been suspended —
            <a href="{{ route('subscription.show') }}" class="underline font-medium">details</a>.
        </div>
    @elseif (auth()->user()->onTrial())
        <div role="status" class="bg-amber-100 text-amber-800 text-sm text-center py-2 flex items-center justify-center gap-2">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            {{ auth()->user()->trialDaysLeft() }} day(s) left in your free trial —
            <a href="{{ route('subscription.show') }}" class="underline font-medium">subscribe</a> to keep uninterrupted access.
        </div>
    @elseif (auth()->user()->subscription_status !== 'active' && ! auth()->user()->isAdmin())
        <div role="alert" class="bg-rose-100 text-rose-800 text-sm text-center py-2 flex items-center justify-center gap-2">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            Your subscription has expired. Please
            <a href="{{ route('subscription.show') }}" class="underline font-medium">renew your subscription</a>
            to continue using premium features.
        </div>
    @endif
@endauth

<div class="md:flex min-h-screen">
@auth
    @php
        $isActive = fn (string $pattern) => request()->routeIs($pattern);
        $linkClass = function (string $pattern) use ($isActive) {
            $active = $isActive($pattern);
            return 'flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm transition-all border-l-[3px] '
                . ($active
                    ? 'bg-[var(--brand-2-tint-25)] text-white font-medium border-[#FFBA00]'
                    : 'text-slate-300 hover:bg-white/5 hover:text-white border-transparent');
        };

        // Grouped/collapsible sidebar — each group's own links reuse
        // $linkClass/$isActive exactly as before, just organized under a
        // toggle button instead of one long flat list. A group starts
        // OPEN if the current page is inside it (computed here, since
        // that needs request()); pmInitSidebarGroups() in the page
        // script further below then layers the user's own
        // remembered open/closed choice (localStorage) on top of that on
        // load, and pmToggleSidebarGroup() keeps it updated as they
        // click.
        $navGroups = [
            'finance' => [
                'label' => 'Finance',
                'icon' => 'fa-solid fa-sack-dollar',
                'items' => [
                    ['route' => 'plans.index', 'pattern' => 'plans.*', 'icon' => 'fa-solid fa-list-check', 'label' => 'Plans'],
                    ['route' => 'incomes.index', 'pattern' => 'incomes.*', 'icon' => 'fa-solid fa-money-bill-trend-up', 'label' => 'Income'],
                    ['route' => 'budgets.index', 'pattern' => 'budgets.*', 'icon' => 'fa-solid fa-wallet', 'label' => 'Budgets'],
                    ['route' => 'expenses.index', 'pattern' => 'expenses.*', 'icon' => 'fa-solid fa-receipt', 'label' => 'Expenses'],
                    ['route' => 'debts.index', 'pattern' => 'debts.*', 'icon' => 'fa-solid fa-hand-holding-dollar', 'label' => 'Debts'],
                    ['route' => 'savings-goals.index', 'pattern' => 'savings-goals.*', 'icon' => 'fa-solid fa-piggy-bank', 'label' => 'Savings Goals'],
                    ['route' => 'savings-contributions.index', 'pattern' => 'savings-contributions.*', 'icon' => 'fa-solid fa-coins', 'label' => 'Contributions'],
                ],
            ],
            'health' => [
                'label' => 'Health & Wellness',
                'icon' => 'fa-solid fa-heart-pulse',
                'items' => [
                    ['route' => 'diet-logs.index', 'pattern' => 'diet-logs.*', 'icon' => 'fa-solid fa-utensils', 'label' => 'Diet'],
                    ['route' => 'exercise-logs.index', 'pattern' => 'exercise-logs.*', 'icon' => 'fa-solid fa-person-running', 'label' => 'Exercise'],
                    ['route' => 'sleep-logs.index', 'pattern' => 'sleep-logs.*', 'icon' => 'fa-solid fa-bed', 'label' => 'Sleep'],
                    ['route' => 'health-checkups.index', 'pattern' => 'health-checkups.*', 'icon' => 'fa-solid fa-stethoscope', 'label' => 'Health'],
                ],
            ],
            'work' => [
                'label' => 'Work & Projects',
                'icon' => 'fa-solid fa-briefcase',
                'items' => [
                    ['route' => 'projects.index', 'pattern' => 'projects.*', 'icon' => 'fa-solid fa-diagram-project', 'label' => 'Projects'],
                    ['route' => 'project-tasks.index', 'pattern' => 'project-tasks.*', 'icon' => 'fa-solid fa-clipboard-check', 'label' => 'Tasks'],
                    ['route' => 'meetings.index', 'pattern' => 'meetings.*', 'icon' => 'fa-solid fa-calendar-days', 'label' => 'Meetings'],
                ],
            ],
            'personal' => [
                'label' => 'Personal Life',
                'icon' => 'fa-solid fa-user-group',
                'items' => [
                    ['route' => 'education-plans.index', 'pattern' => 'education-plans.*', 'icon' => 'fa-solid fa-graduation-cap', 'label' => 'Education'],
                    ['route' => 'network-contacts.index', 'pattern' => 'network-contacts.*', 'icon' => 'fa-solid fa-people-arrows', 'label' => 'Network'],
                    ['route' => 'relationships.index', 'pattern' => 'relationships.*', 'icon' => 'fa-solid fa-heart', 'label' => 'Relationships'],
                    ['route' => 'spiritual-practices.index', 'pattern' => 'spiritual-practices.*', 'icon' => 'fa-solid fa-hands-praying', 'label' => 'Spiritual Growth'],
                ],
            ],
            'productivity' => [
                'label' => 'Productivity & AI',
                'icon' => 'fa-solid fa-brain',
                'items' => [
                    ['route' => 'reminders.index', 'pattern' => 'reminders.*', 'icon' => 'fa-solid fa-bell', 'label' => 'Reminders'],
                    ['route' => 'ai-plans.index', 'pattern' => 'ai-plans.*', 'icon' => 'fa-solid fa-robot', 'label' => 'AI Planner'],
                ],
            ],
            'tools' => [
                'label' => 'Tools & Account',
                'icon' => 'fa-solid fa-toolbox',
                'items' => [
                    ['route' => 'api-credentials.index', 'pattern' => 'api-credentials.*', 'icon' => 'fa-solid fa-key', 'label' => 'API Keys'],
                    ['route' => 'signature.show', 'pattern' => 'signature.*', 'icon' => 'fa-solid fa-signature', 'label' => 'Signatures'],
                    ['route' => 'business-card.edit', 'pattern' => 'business-card.*', 'icon' => 'fa-solid fa-id-card', 'label' => 'Business Card'],
                    ['route' => 'organization.show', 'pattern' => 'organization.*', 'icon' => 'fa-solid fa-building-user', 'label' => 'Organization'],
                    ['route' => 'feedback.index', 'pattern' => 'feedback.*', 'icon' => 'fa-solid fa-comment-dots', 'label' => 'Feedback'],
                    ['route' => 'tips', 'pattern' => 'tips', 'icon' => 'fa-solid fa-lightbulb', 'label' => 'Usage Tips'],
                ],
            ],
        ];

        if (auth()->user()->isAdmin()) {
            $navGroups['admin'] = [
                'label' => 'Admin',
                'icon' => 'fa-solid fa-shield-halved',
                'items' => [
                    ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'icon' => 'fa-solid fa-users', 'label' => 'Users'],
                    ['route' => 'admin.statistics', 'pattern' => 'admin.statistics', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Statistics'],
                    ['route' => 'admin.settings.edit', 'pattern' => 'admin.settings.*', 'icon' => 'fa-solid fa-gear', 'label' => 'Settings'],
                    ['route' => 'admin.payment-gateways.index', 'pattern' => 'admin.payment-gateways.*', 'icon' => 'fa-solid fa-credit-card', 'label' => 'Payment Gateways'],
                    ['route' => 'admin.payments.index', 'pattern' => 'admin.payments.*', 'icon' => 'fa-solid fa-money-check-dollar', 'label' => 'Payments'],
                    ['route' => 'admin.subscription-plans.index', 'pattern' => 'admin.subscription-plans.*', 'icon' => 'fa-solid fa-tags', 'label' => 'Subscription Plans'],
                    ['route' => 'admin.feedback.index', 'pattern' => 'admin.feedback.*', 'icon' => 'fa-solid fa-comment-dots', 'label' => 'Admin Feedback'],
                    ['route' => 'admin.announcements.index', 'pattern' => 'admin.announcements.*', 'icon' => 'fa-solid fa-bullhorn', 'label' => 'Announcements'],
                    ['route' => 'admin.billing-logs.index', 'pattern' => 'admin.billing-logs.*', 'icon' => 'fa-solid fa-file-invoice-dollar', 'label' => 'Billing Activity'],
                    ['route' => 'admin.enterprise-inquiries.index', 'pattern' => 'admin.enterprise-inquiries.*', 'icon' => 'fa-solid fa-handshake', 'label' => 'Enterprise Inquiries'],
                    ['route' => 'admin.invoices.index', 'pattern' => 'admin.invoices.*', 'icon' => 'fa-solid fa-file-invoice', 'label' => 'Quotations & Invoices'],
                ],
            ];
        }

        $groupIsActive = fn (array $group) => collect($group['items'])->contains(fn ($item) => $isActive($item['pattern']));
    @endphp

    <button
        id="sidebar-toggle"
        type="button"
        class="md:hidden m-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg btn-primary text-white text-sm shadow-sm"
        aria-expanded="false"
        aria-controls="sidebar"
    >
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
        <span>Menu</span>
    </button>

    @php
        // Use the stored image bytes for the web avatar preview instead of
        // relying on /storage being publicly reachable on the web server.
        $sidebarAvatarPreview = auth()->user()->avatarDataUri();
    @endphp

    <aside id="sidebar" class="hidden md:flex md:flex-col md:w-64 md:shrink-0 bg-gradient-to-b from-[var(--brand-1)] to-[var(--brand-1-dark)] md:min-h-screen md:sticky md:top-0 md:h-screen">
        <div class="px-4 py-5 border-b border-white/10">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 font-bold text-lg text-white {{ $isActive('profile.edit') ? 'opacity-80' : '' }}" @if($isActive('profile.edit')) aria-current="page" @endif>
                @if ($sidebarAvatarPreview)
                    <img src="{{ $sidebarAvatarPreview }}" alt="" aria-hidden="true"
                         class="w-8 h-8 rounded-full object-cover shrink-0"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span class="w-8 h-8 rounded-full bg-gradient-to-br from-[var(--brand-1)] to-[var(--brand-2)] text-white items-center justify-center text-sm font-bold shrink-0 hidden" aria-hidden="true">
                        {{ auth()->user()->initial() }}
                    </span>
                @else
                    <span class="w-8 h-8 rounded-full bg-gradient-to-br from-[var(--brand-1)] to-[var(--brand-2)] text-white flex items-center justify-center text-sm font-bold shrink-0" aria-hidden="true">
                        {{ auth()->user()->initial() }}
                    </span>
                @endif
                <span class="truncate">{{ auth()->user()->name }}</span>
            </a>
        </div>

        <nav aria-label="Main navigation" class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
            <a href="{{ route('dashboard') }}" class="{{ $linkClass('dashboard') }}" @if($isActive('dashboard')) aria-current="page" @endif>
                <i class="fa-solid fa-gauge-high w-4 text-center" aria-hidden="true"></i> Dashboard
            </a>

            @foreach ($navGroups as $groupKey => $group)
                @php $groupActive = $groupIsActive($group); @endphp
                <div class="pt-1">
                    <button type="button"
                            id="sidebar-group-btn-{{ $groupKey }}"
                            aria-expanded="{{ $groupActive ? 'true' : 'false' }}"
                            aria-controls="sidebar-group-{{ $groupKey }}"
                            data-group="{{ $groupKey }}"
                            onclick="pmToggleSidebarGroup('{{ $groupKey }}')"
                            class="w-full flex items-center gap-3 px-4 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider text-slate-400 hover:text-slate-200 hover:bg-white/5 transition-colors">
                        <i class="{{ $group['icon'] }} w-4 text-center" aria-hidden="true"></i>
                        <span class="flex-1 text-left">{{ $group['label'] }}</span>
                        <i class="fa-solid fa-chevron-down w-3 text-center transition-transform pm-sidebar-chevron" data-group="{{ $groupKey }}" aria-hidden="true"
                           style="{{ $groupActive ? '' : 'transform: rotate(-90deg);' }}"></i>
                    </button>
                    <div id="sidebar-group-{{ $groupKey }}" class="space-y-0.5 pl-2" @if (! $groupActive) hidden @endif>
                        @foreach ($group['items'] as $item)
                            <a href="{{ route($item['route']) }}" class="{{ $linkClass($item['pattern']) }}" @if($isActive($item['pattern'])) aria-current="page" @endif>
                                <i class="{{ $item['icon'] }} w-4 text-center" aria-hidden="true"></i> {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="px-2 py-3 border-t border-white/10 space-y-0.5">
            <a href="{{ route('subscription.show') }}" class="{{ $linkClass('subscription.show') }}" @if($isActive('subscription.show')) aria-current="page" @endif>
                <i class="fa-solid fa-credit-card w-4 text-center" aria-hidden="true"></i> Billing
            </a>
            <a href="{{ route('privacy.show') }}" class="{{ $linkClass('privacy.show') }}" @if($isActive('privacy.show')) aria-current="page" @endif>
                <i class="fa-solid fa-shield-halved w-4 text-center" aria-hidden="true"></i> Privacy &amp; Data
            </a>
            <a href="{{ route('help.show') }}" class="{{ $linkClass('help.show') }}" @if($isActive('help.show')) aria-current="page" @endif>
                <i class="fa-solid fa-circle-question w-4 text-center" aria-hidden="true"></i> Help &amp; FAQ
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-white/5 hover:text-rose-300 transition-all">
                    <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center" aria-hidden="true"></i> Logout
                </button>
            </form>
        </div>
    </aside>
@endauth

    <div class="flex-1 min-w-0">
        <main id="main-content" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            @if (session('success'))
                <div role="status" aria-live="polite" class="pm-flash-message mb-4 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2 transition-opacity duration-700">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    {{ session('success') }}
                </div>
            @endif

            @if (session('warning'))
                <div role="status" aria-live="polite" class="pm-flash-message mb-4 rounded-lg bg-amber-50 border border-amber-100 text-amber-800 px-4 py-3 text-sm flex items-center gap-2 transition-opacity duration-700">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    {{ session('warning') }}
                </div>
            @endif

            @if (session('info'))
                <div role="status" aria-live="polite" class="pm-flash-message mb-4 rounded-lg bg-blue-50 border border-blue-100 text-blue-800 px-4 py-3 text-sm flex items-center gap-2 transition-opacity duration-700">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    {{ session('info') }}
                </div>
            @endif

            @if (session('status'))
                <div role="status" aria-live="polite" class="pm-flash-message mb-4 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2 transition-opacity duration-700">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" aria-live="assertive" class="pm-flash-message mb-4 rounded-lg bg-rose-50 border border-rose-100 text-rose-800 px-4 py-3 text-sm transition-opacity duration-700">
                    <p class="font-medium mb-1 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        Please fix the following:
                    </p>
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-center text-xs text-slate-400">
            <a href="{{ route('privacy-policy') }}" class="underline hover:text-slate-600">Privacy Policy</a>
        </footer>
    </div>
</div>

@auth
    {{--
        In-app "alarm" for reminders — a real-time layer ON TOP OF (not a
        replacement for) the email/database notification the
        `reminders:send` scheduled command already sends. This only helps
        while the app is actually open in a browser tab; it changes
        nothing server-side and never reschedules a reminder itself.
    --}}
    <dialog id="reminder-alarm-modal" aria-labelledby="reminder-alarm-title" class="rounded-2xl p-6 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
            </div>
            <h2 id="reminder-alarm-title" class="text-lg font-bold text-slate-800">Reminder</h2>
        </div>
        <p id="reminder-alarm-message" class="text-sm text-slate-600 mb-5"></p>
        <div class="flex justify-end">
            <button type="button" id="reminder-alarm-dismiss" class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <span>Dismiss</span>
            </button>
        </div>
    </dialog>

    <script>
        (function () {
            var DISMISSED_KEY = 'pm_dismissed_reminder_alarms';
            var DUE_NOW_URL = @json(route('reminders.due-now'));

            function getDismissed() {
                try { return JSON.parse(localStorage.getItem(DISMISSED_KEY) || '[]'); }
                catch (e) { return []; }
            }

            function markDismissed(id) {
                var list = getDismissed();
                if (list.indexOf(id) === -1) { list.push(id); }
                localStorage.setItem(DISMISSED_KEY, JSON.stringify(list));
            }

            // Browsers require a user gesture (click/tap/keypress) to have
            // occurred before an AudioContext can produce AUDIBLE sound —
            // one created outside of a gesture starts in a 'suspended'
            // state and plays silently, with no error thrown. The alarm
            // tone is triggered from an async polling fetch(), not a
            // direct click, so creating-and-immediately-playing-through a
            // brand new context each time it fires (the original
            // approach) silently failed to produce any sound at all.
            //
            // Fix: capture the FIRST interaction anywhere on an
            // authenticated page (virtually guaranteed to happen almost
            // immediately — clicking a link, typing, tapping a button)
            // and create+unlock ONE AudioContext then, reusing that same
            // already-unlocked context whenever an alarm actually needs to
            // play later, instead of creating a fresh one per alarm.
            var pmAudioCtx = null;

            function pmUnlockAudioContext() {
                if (pmAudioCtx) { return; }
                try {
                    var Ctx = window.AudioContext || window.webkitAudioContext;
                    pmAudioCtx = new Ctx();
                } catch (e) {
                    // Web Audio unsupported — the alarm dialog will still
                    // show, just silently, on a browser this old/limited.
                }
            }

            ['click', 'keydown', 'touchstart'].forEach(function (evt) {
                document.addEventListener(evt, pmUnlockAudioContext, { once: true, passive: true });
            });

            function playAlarmTone() {
                // No interaction captured yet this page load (e.g. a tab
                // left open and untouched from before the reminder fired)
                // — try anyway; some browsers still allow it if the SITE
                // (not just this specific page load) was interacted with
                // previously.
                if (!pmAudioCtx) { pmUnlockAudioContext(); }
                if (!pmAudioCtx) { return; }

                try {
                    // A context can also get suspended again on its own
                    // (e.g. some browsers do this for backgrounded tabs) —
                    // resuming is safe to call even when already running.
                    if (pmAudioCtx.state === 'suspended') {
                        pmAudioCtx.resume();
                    }
                    [880, 660].forEach(function (freq, i) {
                        var osc = pmAudioCtx.createOscillator();
                        var gain = pmAudioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = freq;
                        gain.gain.value = 0.15;
                        osc.connect(gain);
                        gain.connect(pmAudioCtx.destination);
                        var start = pmAudioCtx.currentTime + i * 0.25;
                        osc.start(start);
                        osc.stop(start + 0.2);
                    });
                } catch (e) {
                    // Audio is a nice-to-have, not essential — fail silently.
                }
            }

            function showAlarm(reminder) {
                var dialog = document.getElementById('reminder-alarm-modal');
                if (!dialog || dialog.open) { return; }
                document.getElementById('reminder-alarm-title').textContent = reminder.title;
                document.getElementById('reminder-alarm-message').textContent =
                    reminder.message || 'This is your scheduled reminder.';
                dialog.dataset.reminderId = reminder.id;
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                    playAlarmTone();
                }
            }

            function checkDueReminders() {
                fetch(DUE_NOW_URL, { headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : []; })
                    .then(function (reminders) {
                        var dismissed = getDismissed();
                        var due = (reminders || []).filter(function (r) {
                            return dismissed.indexOf(r.id) === -1;
                        });
                        if (due.length > 0) { showAlarm(due[0]); }
                    })
                    .catch(function () { /* a network blip shouldn't spam errors */ });
            }

            document.addEventListener('DOMContentLoaded', function () {
                var dismissBtn = document.getElementById('reminder-alarm-dismiss');
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', function () {
                        var dialog = document.getElementById('reminder-alarm-modal');
                        markDismissed(parseInt(dialog.dataset.reminderId, 10));
                        dialog.close();
                    });
                }

                checkDueReminders();
                setInterval(checkDueReminders, 60000);
            });
        })();
    </script>
@endauth

{{--
    Voice dictation (speech-to-text) for every text/textarea field rendered
    by crud/_fields.blade.php — used by all tracking modules' create/edit
    modals and full-page fallbacks. Progressive enhancement: mic buttons
    are rendered hidden and only revealed here if the browser actually
    supports the Web Speech API (notably: not supported in Firefox as of
    this writing). No server round-trip — recognition runs entirely in
    the browser.

    Continuous, not single-utterance: recognition.continuous = true keeps
    listening across pauses in speech rather than stopping after the first
    thing said. It only stops when the user (a) clicks the same mic button
    again (a toggle), (b) clicks anywhere else — another button, another
    field, another mic — or (c) moves focus to a different field, e.g. via
    Tab. Only one field can be actively dictated into at a time; starting
    on a new one automatically stops whatever was running before.
--}}
<script>
    var pmActiveRecognition = null;
    var pmActiveButton = null;
    var pmActiveField = null;

    function pmStopDictation() {
        if (pmActiveRecognition) {
            pmActiveRecognition.stop();
        }
    }

    function pmStartDictation(fieldId, buttonEl) {
        var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) { return; }

        // Clicking the mic that's already listening stops it — a toggle,
        // not just "start."
        if (pmActiveButton === buttonEl) {
            pmStopDictation();
            return;
        }

        // Switching to a different field's mic stops whatever was
        // previously listening first, rather than running two at once.
        pmStopDictation();

        var field = document.getElementById(fieldId);
        if (!field) { return; }

        var recognition = new SpeechRecognition();
        recognition.lang = document.documentElement.lang || 'en-US';
        recognition.continuous = true;
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        pmActiveRecognition = recognition;
        pmActiveButton = buttonEl;
        pmActiveField = field;

        buttonEl.setAttribute('aria-pressed', 'true');
        buttonEl.classList.add('text-rose-600');
        buttonEl.classList.remove('text-slate-400');

        // Some browsers' continuous=true mode internally restarts the
        // recognition session after a pause (a known Web Speech API
        // quirk, especially on mobile Chrome), and event.resultIndex
        // bookkeeping isn't always reliable across that restart — it can
        // report an already-processed index as "new" again, appending
        // the same phrase a second time. Tracking our OWN highest
        // processed index (independent of whatever the browser reports)
        // guards against that: an index is only ever appended once,
        // no matter what resultIndex says.
        var lastProcessedIndex = -1;

        recognition.onresult = function (event) {
            var startAt = Math.max(event.resultIndex, lastProcessedIndex + 1);

            for (var i = startAt; i < event.results.length; i++) {
                if (event.results[i].isFinal) {
                    var transcript = event.results[i][0].transcript;
                    field.value = field.value ? (field.value.replace(/\s+$/, '') + ' ' + transcript) : transcript;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
                lastProcessedIndex = i;
            }
        };

        var reset = function () {
            buttonEl.setAttribute('aria-pressed', 'false');
            buttonEl.classList.remove('text-rose-600');
            buttonEl.classList.add('text-slate-400');
            if (pmActiveRecognition === recognition) {
                pmActiveRecognition = null;
                pmActiveButton = null;
                pmActiveField = null;
            }
        };
        recognition.onend = reset;
        recognition.onerror = reset;

        recognition.start();
    }

    // Stops dictation the moment the user clicks ANYTHING else — another
    // button (Save/Cancel/a different mic), another field, anywhere on
    // the page — matching "continuously until a user stops or clicks in
    // next field or button."
    document.addEventListener('click', function (event) {
        if (!pmActiveButton) { return; }
        if (event.target === pmActiveButton || pmActiveButton.contains(event.target)) { return; }
        pmStopDictation();
    }, true);

    // Also stops on a focus change that isn't from a click (e.g. Tab to
    // the next field).
    document.addEventListener('focusin', function (event) {
        if (!pmActiveField) { return; }
        if (event.target !== pmActiveField) {
            pmStopDictation();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        var supported = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
        if (!supported) { return; }
        document.querySelectorAll('.pm-voice-input-btn').forEach(function (btn) {
            btn.classList.remove('hidden');
        });
    });
</script>

<script>
    (function () {
        var toggle = document.getElementById('sidebar-toggle');
        var sidebar = document.getElementById('sidebar');
        if (!toggle || !sidebar) { return; }

        toggle.addEventListener('click', function () {
            var isHidden = sidebar.classList.contains('hidden');
            if (isHidden) {
                sidebar.classList.remove('hidden');
                sidebar.classList.add('flex', 'flex-col');
            } else {
                sidebar.classList.add('hidden');
                sidebar.classList.remove('flex', 'flex-col');
            }
            toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });
    })();
</script>

<script>
    // Sidebar group dropdowns — server-rendered initial open/closed state
    // (see $groupIsActive in the sidebar markup) reflects "is the current
    // page in this group", then this layers the user's own last choice
    // (localStorage) on top on load, so a group they deliberately left
    // open stays open across page navigations even if the new page isn't
    // in that group.
    function pmToggleSidebarGroup(groupKey) {
        var panel = document.getElementById('sidebar-group-' + groupKey);
        var btn = document.getElementById('sidebar-group-btn-' + groupKey);
        var chevron = document.querySelector('.pm-sidebar-chevron[data-group="' + groupKey + '"]');
        if (!panel || !btn) { return; }

        var willOpen = panel.hasAttribute('hidden');
        if (willOpen) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute('hidden', '');
        }
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (chevron) { chevron.style.transform = willOpen ? '' : 'rotate(-90deg)'; }

        try {
            localStorage.setItem('pm-sidebar-group-' + groupKey, willOpen ? '1' : '0');
        } catch (e) { /* localStorage unavailable — state just won't persist across page loads */ }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.pm-sidebar-chevron').forEach(function (chevron) {
            var groupKey = chevron.dataset.group;
            var stored = null;
            try { stored = localStorage.getItem('pm-sidebar-group-' + groupKey); } catch (e) { /* ignore */ }
            if (stored === null) { return; } // no saved preference — keep the server-rendered (active-page-based) state

            var panel = document.getElementById('sidebar-group-' + groupKey);
            var btn = document.getElementById('sidebar-group-btn-' + groupKey);
            if (!panel || !btn) { return; }

            var shouldBeOpen = stored === '1';
            if (shouldBeOpen) {
                panel.removeAttribute('hidden');
                chevron.style.transform = '';
            } else {
                panel.setAttribute('hidden', '');
                chevron.style.transform = 'rotate(-90deg)';
            }
            btn.setAttribute('aria-expanded', shouldBeOpen ? 'true' : 'false');
        });
    });
</script>

<script>
    // Success/warning/info/error banners (session('success'), $errors,
    // etc. — see the flash message block near the top of <main>) show
    // for 5 seconds, then fade out over 0.7s and get removed from the
    // DOM (rather than just hidden) so they don't leave empty spacing
    // behind or get read again by a screen reader on some later DOM change.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.pm-flash-message').forEach(function (message) {
            setTimeout(function () {
                message.style.opacity = '0';
                message.addEventListener('transitionend', function () {
                    message.remove();
                }, { once: true });
            }, 5000);
        });
    });
</script>

</body>
</html>
