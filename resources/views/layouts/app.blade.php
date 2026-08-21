<!DOCTYPE html>
<html lang="en">
@php
    // Falls back gracefully if AppServiceProvider hasn't been replaced yet
    // (see README)  the app still renders with defaults instead of a hard
    // "Undefined variable $siteSettings" error on every single page.
    $siteSettings = $siteSettings ?? new \App\Models\SiteSetting(['site_name' => 'Personal Monitor']);

    // Personal accent color (see User::themeColor() and friends)  falls
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap">
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
        body { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        h1, h2, h3, h4, h5, h6, .pm-heading { font-family: 'Outfit', 'Poppins', sans-serif; }
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
           hover:from-[var(--brand-1-dark)] hover:to-[var(--brand-2-dark)]) 
           on request, every primary button app-wide is now a SOLID color,
           not a gradient, and this is the same reliable-regardless-of-
           Tailwind-JIT approach already used for the login/register/verify
           buttons. See README's "All buttons: solid, not gradient" section.
           Hover swaps to the SECONDARY brand color outright (not a darker
           shade of the same primary color)  also on request. */
        .btn-primary {
            background-color: var(--brand-1);
        }
        .btn-primary:hover {
            background-color: var(--brand-2);
        }

        /* Plain CSS rather than Tailwind's border-slate-300/shadow-sm
           utilities for the same reliability reason  form inputs were
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

        /* Lighter secondary (sage)-tinted PAGE BACKGROUND  cards/tables
           sit on top of it in plain white, so the tint shows in the gaps
           and margins around them rather than tinting the cards
           themselves. (An earlier iteration did the reverse  white page,
           tinted cards  this swaps it per request.) Plain CSS for the
           same reliability reason as .pm-input/.btn-primary above. */
        .pm-card-bg {
            background-color: #ffffff;
        }

        /* Plain CSS rather than Tailwind's w-[92vw]/w-[88vw] bracket-
           arbitrary-value classes for every <dialog>'s mobile width  the
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

        /* A visibly SMALLER, lighter treatment than the standard
           .pm-dialog  used specifically when the create modal is opened
           from a calendar day click, to feel closer to the compact
           "quick add event" popup Google Calendar/Teams show, rather than
           a full-page-feeling form dialog. Same fields, same form 
           purely a sizing/spacing difference. */

        /* Apple-inspired 2026 visual language: quiet depth, large radii,
           translucent surfaces, compact controls and strong icon hierarchy. */
        :root{--apple-ink:#111827;--apple-muted:#667085;--apple-bg:#f4f6f8;--apple-line:rgba(15,23,42,.08);--apple-shadow:0 14px 38px rgba(15,23,42,.08);}
        body{background:radial-gradient(circle at 18% 0%,rgba(0,137,123,.08),transparent 30%),linear-gradient(180deg,#f8fafc 0%,#f2f5f7 100%)!important;}
        main a{text-decoration:none!important;}
        .apple-page{max-width:1500px;margin:0 auto;}
        .apple-hero{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:24px 26px;border:1px solid rgba(255,255,255,.7);border-radius:24px;background:rgba(255,255,255,.76);box-shadow:var(--apple-shadow);backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px)}
        .apple-hero h1{font-size:clamp(1.7rem,3vw,2.35rem);font-weight:800;letter-spacing:-.035em;color:var(--apple-ink);margin:.15rem 0}.apple-hero p{color:var(--apple-muted);max-width:760px;font-size:.92rem}
        .apple-eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.68rem;font-weight:800;color:#7c8798}
        .apple-surface{background:rgba(255,255,255,.84);border:1px solid rgba(255,255,255,.88);border-radius:22px;padding:20px;box-shadow:0 8px 28px rgba(15,23,42,.055);backdrop-filter:blur(18px)}
        .apple-surface h2,.apple-surface h3{color:var(--apple-ink);font-weight:800;letter-spacing:-.02em}.apple-surface p{color:var(--apple-muted);font-size:.88rem;line-height:1.6}
        .apple-dark-card{background:linear-gradient(145deg,#17191d,#252a31);color:white;border-color:rgba(255,255,255,.08);box-shadow:0 18px 42px rgba(15,23,42,.18)}.apple-dark-card .apple-muted,.apple-dark-card .apple-caption{color:#aeb7c4}
        .apple-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.apple-big{font-size:2.25rem;line-height:1;font-weight:850;letter-spacing:-.05em;margin:.4rem 0}.apple-big.small{font-size:1.7rem;color:#111827}
        .apple-muted{font-size:.78rem;color:var(--apple-muted);margin-top:.9rem}.apple-caption{font-size:.76rem;color:#98a2b3;margin-top:.55rem}.apple-progress{height:7px;background:rgba(255,255,255,.14);border-radius:99px;overflow:hidden;margin-top:14px}.apple-progress span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#66e3c4,#8da8ff)}
        .apple-icon-chip{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:rgba(255,255,255,.1);color:white;font-size:1rem}.apple-icon-chip.light{background:#eef2f6;color:#4b5563}
        .apple-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:40px;padding:0 15px;border-radius:12px;border:1px solid rgba(15,23,42,.1);background:#fff;color:#1f2937;font-size:.82rem;font-weight:750;box-shadow:0 3px 10px rgba(15,23,42,.05);transition:.16s ease}.apple-btn:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(15,23,42,.09)}.apple-btn-primary{background:#16191e;color:#fff;border-color:#16191e}.apple-btn-small{min-height:34px;padding:0 11px;font-size:.75rem}.apple-btn.danger{color:#c92a4c;background:#fff4f6;border-color:#ffd6df}.apple-link{font-size:.82rem;font-weight:750;color:var(--brand-1)}
        .apple-section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}.apple-table-wrap{overflow:auto;border:1px solid #edf0f3;border-radius:16px}.apple-table{width:100%;border-collapse:collapse;font-size:.82rem}.apple-table th{text-align:left;padding:11px 13px;background:#f8fafc;color:#778195;font-size:.68rem;text-transform:uppercase;letter-spacing:.07em}.apple-table td{padding:13px;border-top:1px solid #eef1f4;color:#455064}.apple-actions{display:flex;justify-content:flex-end;gap:7px}.apple-empty{display:flex;flex-direction:column;align-items:center;text-align:center;padding:34px;color:#98a2b3}.apple-empty i{font-size:1.8rem;color:#43b89e;margin-bottom:8px}.apple-empty strong{color:#344054}.apple-empty span{font-size:.8rem;margin-top:3px}.apple-alert{background:#ecfdf5;color:#087f5b;border:1px solid #bbf7d0;border-radius:14px;padding:12px 15px;font-size:.82rem}
        .pm-input,input:not([type=checkbox]):not([type=radio]),select,textarea{border-radius:12px!important;border-color:#dce2e8!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.7)!important}.btn-primary{border-radius:12px!important;box-shadow:0 7px 18px rgba(0,137,123,.18)!important}.pm-card-bg{border-radius:20px!important;box-shadow:0 8px 26px rgba(15,23,42,.055)!important}
        @media(max-width:900px){.apple-grid-3{grid-template-columns:1fr}.apple-hero{align-items:flex-start;flex-direction:column}.apple-hero .apple-btn{width:100%}}

</style>
<style id="pm-motion-system">
    :root{--pm-motion-fast:180ms;--pm-motion-normal:420ms;--pm-motion-slow:760ms;--pm-motion-spring:cubic-bezier(.2,.8,.2,1)}
    @keyframes pmFloatIn{from{opacity:0;transform:translateY(14px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}
    @keyframes pmIconPop{0%{transform:scale(.92) rotate(-3deg)}55%{transform:scale(1.12) rotate(2deg)}100%{transform:scale(1) rotate(0)}}
    @keyframes pmChartReveal{from{opacity:0;transform:translateY(12px) scale(.985);filter:blur(2px)}to{opacity:1;transform:none;filter:none}}
    .pm-motion-enter{opacity:0;transform:translateY(14px)}
    .pm-motion-enter.pm-motion-visible{animation:pmFloatIn var(--pm-motion-normal) var(--pm-motion-spring) both}
    button,.btn,.btn-primary,.btn-secondary,a[class*="btn-"]{transition:transform var(--pm-motion-fast) var(--pm-motion-spring),box-shadow var(--pm-motion-fast) ease,filter var(--pm-motion-fast) ease!important;will-change:transform}
    button:hover,.btn:hover,.btn-primary:hover,.btn-secondary:hover,a[class*="btn-"]:hover{transform:translateY(-1px)}
    button:active,.btn:active,.btn-primary:active,.btn-secondary:active,a[class*="btn-"]:active{transform:translateY(0) scale(.97)}
    .quick-card,.stat-card,.pm-stat-card,.card,[class*="summary-card"]{transition:transform var(--pm-motion-fast) var(--pm-motion-spring),box-shadow var(--pm-motion-fast) ease!important}
    .quick-card:hover .quick-icon,.card:hover .fa-solid,.card:hover .fa-regular,.card:hover .fa-brands{animation:pmIconPop 460ms var(--pm-motion-spring)}
    canvas,.chart-container,.apexcharts-canvas,.chartjs-render-monitor,.recharts-wrapper{opacity:0}
    canvas.pm-motion-visible,.chart-container.pm-motion-visible,.apexcharts-canvas.pm-motion-visible,.chartjs-render-monitor.pm-motion-visible,.recharts-wrapper.pm-motion-visible{animation:pmChartReveal var(--pm-motion-slow) var(--pm-motion-spring) both}
    .chart-container svg path,.apexcharts-canvas svg path{transition:stroke-dashoffset .9s ease,opacity .5s ease}
    .chart-container svg rect,.apexcharts-canvas svg rect{transform-box:fill-box;transform-origin:center bottom;transition:transform .65s var(--pm-motion-spring),opacity .45s ease}
    .pm-counting{font-variant-numeric:tabular-nums;display:inline-block;min-width:1ch}
    @media (prefers-reduced-motion:reduce){
        *,*::before,*::after{animation-duration:.001ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.001ms!important}
        .pm-motion-enter,canvas,.chart-container,.apexcharts-canvas,.chartjs-render-monitor,.recharts-wrapper{opacity:1!important;transform:none!important;filter:none!important}
    }
</style>

<style id="pm-layout-responsive">
    html, body {
        width: 100%;
        max-width: 100%;
        min-height: 100%;
        overflow-x: hidden;
    }

    #pm-app-shell {
        width: 100%;
        min-height: 100vh;
        min-height: 100dvh;
    }

    #main-content {
        min-width: 0;
        width: 100%;
    }

    img, video, canvas, svg {
        max-width: 100%;
    }

    [role="tablist"] {
        max-width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }

    .pm-responsive-table,
    .overflow-x-auto {
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
    }

    @media (min-width: 768px) {
        #pm-app-shell {
            display: block !important;
        }

        #sidebar {
            position: fixed !important;
            top: 0 !important;
            right: auto !important;
            bottom: 0 !important;
            left: 0 !important;
            z-index: 40 !important;

            display: flex !important;
            flex-direction: column !important;

            width: 16rem !important;
            min-width: 16rem !important;
            max-width: 16rem !important;

            height: 100vh !important;
            height: 100dvh !important;
            min-height: 100vh !important;
            min-height: 100dvh !important;
            max-height: 100dvh !important;

            overflow: hidden !important;
        }

        #sidebar nav {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
        }

        #pm-app-shell > .flex-1 {
            display: block !important;
            width: calc(100% - 16rem) !important;
            min-width: 0 !important;
            min-height: 100vh !important;
            min-height: 100dvh !important;
            margin-left: 16rem !important;
        }

        #main-content {
            width: 100% !important;
            min-height: calc(100dvh - 1px) !important;
        }
    }

    @media (max-width: 767.98px) {
        #pm-app-shell,
        #pm-app-shell > .flex-1,
        #main-content {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            margin-left: 0 !important;
        }

        #main-content {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
            overflow-x: hidden !important;
        }

        .grid.grid-cols-2:not(.pm-keep-two-columns),
        .grid.grid-cols-3:not(.pm-keep-three-columns),
        .grid.grid-cols-4:not(.pm-keep-four-columns) {
            grid-template-columns: minmax(0, 1fr) !important;
        }
    }
</style>


{{-- Global modal system: keep this stylesheet LAST so page-specific legacy
     rules cannot move dialogs off-screen. --}}

<style id="pm-horizontal-phone-tables-20260819">
    /*
    |--------------------------------------------------------------------------
    | Phone horizontal data tables
    |--------------------------------------------------------------------------
    | Do not turn rows/cells into cards or narrow columns. The page remains
    | viewport-width while the table itself scrolls horizontally inside its
    | own bounded region.
    */
    .pm-horizontal-table-wrap {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        -webkit-overflow-scrolling: touch !important;
        overscroll-behavior-inline: contain;
        scrollbar-width: thin;
    }

    .pm-horizontal-data-table {
        display: table !important;
        width: max-content !important;
        min-width: 100% !important;
        max-width: none !important;
        table-layout: auto !important;
        border-collapse: collapse !important;
    }

    .pm-horizontal-data-table thead {
        display: table-header-group !important;
    }

    .pm-horizontal-data-table tbody {
        display: table-row-group !important;
    }

    .pm-horizontal-data-table tr {
        display: table-row !important;
    }

    .pm-horizontal-data-table th,
    .pm-horizontal-data-table td {
        display: table-cell !important;
        width: auto !important;
        max-width: none !important;
        vertical-align: top !important;
    }

    .pm-goals-horizontal-table {
        min-width: 1480px !important;
    }

    .pm-invoices-horizontal-table {
        min-width: 1080px !important;
    }

    .pm-receipts-horizontal-table {
        min-width: 900px !important;
    }

    .pm-enterprise-inquiries-horizontal-table {
        min-width: 1120px !important;
    }

    @media (max-width: 767.98px) {
        html,
        body {
            max-width: 100%;
            overflow-x: hidden;
        }

        .pm-horizontal-table-wrap {
            margin-left: 0 !important;
            margin-right: 0 !important;
            border-radius: 12px !important;
        }

        .pm-horizontal-data-table th,
        .pm-horizontal-data-table td {
            white-space: nowrap !important;
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        .pm-horizontal-data-table td.pm-table-wrap-text,
        .pm-horizontal-data-table th.pm-table-wrap-text {
            min-width: 220px !important;
            max-width: 300px !important;
            white-space: normal !important;
            overflow-wrap: anywhere !important;
        }
    }
</style>

<link rel="stylesheet" href="{{ asset('css/modal-responsive.css') }}">
<link rel="stylesheet" href="{{ asset('css/professional-forms.css') }}">

<style id="pm-phone-authoritative-20260819">
/*
|--------------------------------------------------------------------------
| My Digital Diary  final phone responsiveness layer
|--------------------------------------------------------------------------
| Loaded last in <head>. These rules deliberately override old page/module
| widths without changing desktop sidebar behaviour.
*/
@media (max-width: 767.98px) {
    html,
    body {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: hidden !important;
    }

    body {
        margin: 0 !important;
    }

    #pm-app-shell,
    #pm-app-shell > .flex-1,
    #main-content,
    main,
    main > *,
    .apple-page,
    .apple-surface,
    .pm-card-bg,
    .card,
    .dashboard-card,
    section {
        min-width: 0 !important;
        max-width: 100% !important;
    }

    #pm-app-shell {
        display: block !important;
    }

    #pm-app-shell > .flex-1 {
        width: 100% !important;
        margin-left: 0 !important;
    }

    #main-content {
        width: 100% !important;
        padding: 10px !important;
        margin: 0 !important;
        overflow-x: hidden !important;
    }

    /*
     * Any legacy module that has a fixed width/min-width must shrink to the
     * available phone viewport instead of creating the narrow clipped column
     * shown in Meetings Calendar.
     */
    #main-content [style*="width"],
    #main-content [style*="min-width"],
    #main-content .container,
    #main-content .max-w-screen-xl,
    #main-content .max-w-7xl,
    #main-content .max-w-6xl,
    #main-content .max-w-5xl,
    #main-content .max-w-4xl,
    #main-content .max-w-3xl,
    #main-content .max-w-2xl {
        max-width: 100% !important;
        min-width: 0 !important;
    }

    /*
     * Forms and card grids become one useful column on phones.
     * Explicit .pm-keep-* classes may still opt out.
     */
    #main-content .grid.grid-cols-2:not(.pm-keep-two-columns),
    #main-content .grid.grid-cols-3:not(.pm-keep-three-columns),
    #main-content .grid.grid-cols-4:not(.pm-keep-four-columns),
    #main-content .grid.grid-cols-5,
    #main-content .grid.grid-cols-6 {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    /*
     * Meeting/calendar modules.
     */
    .fc,
    .fc-media-screen,
    .fc-view-harness,
    .fc-view-harness-active,
    .fc-daygrid,
    .fc-scrollgrid,
    .meeting-calendar,
    .meetings-calendar,
    [class*="meeting-calendar"],
    [class*="calendar-wrap"],
    [class*="calendar-container"] {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    .fc .fc-toolbar {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: 6px !important;
    }

    .fc .fc-toolbar-chunk {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 4px !important;
        min-width: 0 !important;
    }

    .fc .fc-toolbar-title {
        font-size: 1rem !important;
        line-height: 1.2 !important;
    }

    .fc .fc-button {
        padding: .35rem .5rem !important;
        font-size: .72rem !important;
    }

    .fc .fc-scrollgrid,
    .fc table {
        table-layout: fixed !important;
        width: 100% !important;
        min-width: 0 !important;
    }

    .fc .fc-col-header-cell-cushion,
    .fc .fc-daygrid-day-number {
        font-size: .68rem !important;
        padding: 3px !important;
    }

    .fc .fc-daygrid-event {
        max-width: 100% !important;
        overflow: hidden !important;
        font-size: .62rem !important;
    }

    .fc .fc-event-title,
    .fc .fc-event-time {
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    /*
     * Ordinary data tables should scroll inside their own surface rather
     * than making the entire page wider than the phone.
     */
    .table-responsive,
    .pm-responsive-table,
    .apple-table-wrap,
    .overflow-x-auto {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }

    table {
        max-width: none;
    }

    /*
     * Header/button groups can wrap instead of overflowing.
     */
    .apple-hero,
    .apple-section-head,
    .page-header,
    .toolbar,
    .actions,
    .form-actions {
        max-width: 100% !important;
        flex-wrap: wrap !important;
    }

    /*
     * Native dialogs: phone-safe gutters, internal scrolling and no clipped
     * footer buttons. Keep dialog:not([open]) in modal-responsive.css.
     */
    dialog[open] {
        position: fixed !important;
        top: 50% !important;
        right: 8px !important;
        bottom: auto !important;
        left: 8px !important;
        width: auto !important;
        max-width: none !important;
        max-height: calc(100dvh - 16px) !important;
        margin: 0 !important;
        transform: translateY(-50%) !important;
        overflow: hidden !important;
    }

    dialog[open] > form,
    dialog[open] > .pm-modal-content,
    dialog[open] .pm-modal-content {
        width: 100% !important;
        max-width: 100% !important;
        max-height: calc(100dvh - 16px) !important;
        overflow: hidden !important;
    }

    .pm-modal-body,
    dialog .pm-modal-body {
        min-height: 0 !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }

    .pm-modal-footer,
    dialog .pm-modal-footer {
        flex-wrap: wrap !important;
        gap: 8px !important;
        padding: 12px !important;
    }

    .pm-modal-footer > button,
    .pm-modal-footer > a {
        max-width: 100% !important;
    }

    /*
     * Reminder notification dialog from the screenshot.
     */
    #reminder-alarm-modal[open] {
        padding: 16px !important;
        overflow: visible !important;
    }

    #reminder-alarm-modal[open] > .flex.justify-end {
        width: 100% !important;
        padding: 0 !important;
    }

    #reminder-alarm-dismiss {
        width: 100% !important;
        max-width: 100% !important;
        min-height: 46px !important;
        justify-content: center !important;
        margin: 0 !important;
    }

    /*
     * Text/images must not force a horizontal overflow.
     */
    p,
    h1,
    h2,
    h3,
    h4,
    h5,
    h6,
    a,
    button,
    td,
    th {
        overflow-wrap: anywhere;
    }

    img,
    video,
    canvas,
    svg,
    iframe {
        max-width: 100% !important;
    }
}

@media (max-width: 380px) {
    #main-content {
        padding: 7px !important;
    }

    dialog[open] {
        right: 5px !important;
        left: 5px !important;
        max-height: calc(100dvh - 10px) !important;
    }

    .fc .fc-toolbar-title {
        font-size: .9rem !important;
    }
}
</style>



<style id="pm-admin-horizontal-tables-20260819">
    /*
    |--------------------------------------------------------------------------
    | ALL ADMIN TABLES — PHONE HORIZONTAL MODE
    |--------------------------------------------------------------------------
    | This rule is intentionally global for admin.* routes. It overrides any
    | legacy responsive-table/card CSS that changes rows or cells into blocks.
    | Only the table region scrolls; the page itself remains phone-width.
    */
    .pm-admin-page .pm-admin-table-scroll {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        -webkit-overflow-scrolling: touch !important;
        overscroll-behavior-inline: contain !important;
        scrollbar-width: thin;
    }

    .pm-admin-page #main-content table.pm-admin-horizontal-table {
        display: table !important;
        width: max-content !important;
        min-width: 960px !important;
        max-width: none !important;
        table-layout: auto !important;
        border-collapse: collapse !important;
    }

    .pm-admin-page #main-content table.pm-admin-horizontal-table thead {
        display: table-header-group !important;
    }

    .pm-admin-page #main-content table.pm-admin-horizontal-table tbody {
        display: table-row-group !important;
    }

    .pm-admin-page #main-content table.pm-admin-horizontal-table tfoot {
        display: table-footer-group !important;
    }

    .pm-admin-page #main-content table.pm-admin-horizontal-table tr {
        display: table-row !important;
        width: auto !important;
        max-width: none !important;
    }

    .pm-admin-page #main-content table.pm-admin-horizontal-table th,
    .pm-admin-page #main-content table.pm-admin-horizontal-table td {
        display: table-cell !important;
        width: auto !important;
        min-width: max-content !important;
        max-width: none !important;
        white-space: nowrap !important;
        overflow-wrap: normal !important;
        word-break: normal !important;
        writing-mode: horizontal-tb !important;
        vertical-align: middle !important;
    }

    .pm-admin-page #main-content table.pm-admin-horizontal-table
    .pm-table-wrap-text {
        min-width: 220px !important;
        max-width: 320px !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }

    @media (max-width: 767.98px) {
        .pm-admin-page #main-content {
            overflow-x: hidden !important;
        }

        .pm-admin-page .pm-admin-table-scroll {
            border-radius: 12px !important;
        }

        .pm-admin-page #main-content table.pm-admin-horizontal-table th,
        .pm-admin-page #main-content table.pm-admin-horizontal-table td {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        .pm-admin-table-swipe-hint {
            display: flex !important;
            align-items: center !important;
            gap: 5px !important;
            margin: 0 0 7px !important;
            color: #94a3b8 !important;
            font-size: 11px !important;
            font-weight: 600 !important;
        }
    }

    @media (min-width: 768px) {
        .pm-admin-table-swipe-hint {
            display: none !important;
        }
    }
</style>

<style id="pm-professional-layout-shell">
    :root {
        --pm-page-bg: #f5f7f8;
        --pm-surface: rgba(255,255,255,.94);
        --pm-border: rgba(15,23,42,.08);
        --pm-text: #172033;
        --pm-muted: #667085;
        --pm-radius: 18px;
        --pm-shadow: 0 12px 34px rgba(15,23,42,.07);
    }
    body.pm-professional-shell {
        margin: 0;
        color: var(--pm-text);
        background: radial-gradient(circle at 12% 0%, var(--brand-1-tint-10), transparent 30%), var(--pm-page-bg) !important;
        min-height: 100vh;
        min-height: 100dvh;
    }
    #main-content {
        position: relative;
    }
    #main-content > .pm-flash-message {
        border-radius: 14px !important;
        box-shadow: 0 6px 20px rgba(15,23,42,.045);
    }
    #sidebar {
        background: linear-gradient(180deg, var(--brand-1) 0%, var(--brand-1-dark) 100%) !important;
        box-shadow: 12px 0 30px rgba(15,23,42,.08);
    }
    #sidebar a, #sidebar button { text-decoration: none !important; }
    #sidebar-toggle {
        position: sticky;
        top: 10px;
        z-index: 45;
        min-height: 42px;
        border: 1px solid rgba(255,255,255,.22);
        box-shadow: 0 8px 24px rgba(15,23,42,.14);
    }
    .pm-layout-footer {
        color: #7b8794;
    }
    .pm-layout-footer a {
        color: #5f6b7a;
        font-weight: 600;
        text-decoration: none !important;
    }
    .pm-layout-footer a:hover { color: var(--brand-1); }
    @media (max-width: 767.98px) {
        #main-content { padding: 12px !important; }
        #sidebar-toggle { margin: 10px 12px 2px !important; }
        .pm-layout-footer { padding: 22px 14px 28px !important; }
    }
</style>

<style id="pm-global-scrollable-modals-20260820">
    /* Final layout guard: modal-responsive.css owns geometry/scrolling. */
    html body dialog[open] {
        min-height: 0 !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        -webkit-overflow-scrolling: touch !important;
    }

    html body dialog[open] > form,
    html body dialog[open] > .pm-modal-content,
    html body dialog[open] > .pm-modal-panel,
    html body dialog[open] > .modal-content,
    html body dialog[open] .pm-modal-form {
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        overflow: hidden !important;
    }

    html body dialog[open] .pm-modal-body,
    html body dialog[open] .modal-body,
    html body dialog[open] .modal-scroll-body,
    html body dialog[open] [data-modal-scroll],
    html body dialog[open] form > .overflow-y-auto,
    html body dialog[open] > .overflow-y-auto {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }

    @media (min-width: 641px) {
        html body dialog[open] {
            max-height: calc(var(--pm-modal-vh, 100dvh) - 40px) !important;
        }

        html body dialog[open] > form,
        html body dialog[open] > .pm-modal-content,
        html body dialog[open] > .pm-modal-panel,
        html body dialog[open] > .modal-content,
        html body dialog[open] .pm-modal-form {
            max-height: calc(var(--pm-modal-vh, 100dvh) - 40px) !important;
        }
    }

    @media (max-width: 640px) {
        html body dialog[open] {
            top: max(8px, env(safe-area-inset-top)) !important;
            right: max(8px, env(safe-area-inset-right)) !important;
            bottom: max(8px, env(safe-area-inset-bottom)) !important;
            left: max(8px, env(safe-area-inset-left)) !important;
            width: auto !important;
            height: auto !important;
            max-width: none !important;
            max-height: none !important;
            margin: 0 !important;
            transform: none !important;
        }

        html body dialog[open] > form,
        html body dialog[open] > .pm-modal-content,
        html body dialog[open] > .pm-modal-panel,
        html body dialog[open] > .modal-content,
        html body dialog[open] .pm-modal-form {
            width: 100% !important;
            height: 100% !important;
            max-height: 100% !important;
        }
    }
</style>


    {{-- Global professional 12-hour time controls. Database values remain canonical H:i / datetime-local. --}}
    <link rel="stylesheet" href="{{ asset('css/time-12h.css') }}">
@stack('styles')
</head>
<body class="pm-professional-shell text-slate-800 {{ request()->routeIs('admin.*') ? 'pm-admin-page' : '' }}">

@include('partials.accessibility-widget')
@include('partials.password-toggle')

<a href="#main-content" class="sr-only-focusable bg-[var(--brand-1)] text-white px-4 py-2 rounded-md z-50 fixed top-2 left-2">
    Skip to main content
</a>

@auth
    @if (auth()->user()->isSuspended())
        <div role="alert" class="bg-rose-100 text-rose-800 text-sm text-center py-2 flex items-center justify-center gap-2">
            <i class="fa-solid fa-ban" aria-hidden="true"></i>
            Your account has been suspended 
            <a href="{{ route('subscription.show') }}" class="underline font-medium">details</a>.
        </div>
    @elseif (auth()->user()->onTrial())
        <div role="status" class="bg-amber-100 text-amber-800 text-sm text-center py-2 flex items-center justify-center gap-2">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>
            {{ auth()->user()->trialDaysLeft() }} day(s) left in your free trial 
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

<div id="pm-app-shell" class="min-h-screen md:flex md:items-stretch">
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

        // Grouped/collapsible sidebar  each group's own links reuse
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
                    ['route' => 'financial-planner.index', 'pattern' => 'financial-planner.*', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Financial Planner'],
                    ['route' => 'incomes.index', 'pattern' => 'incomes.*', 'icon' => 'fa-solid fa-money-bill-trend-up', 'label' => 'Income'],
                    ['route' => 'budgets.index', 'pattern' => 'budgets.*', 'icon' => 'fa-solid fa-wallet', 'label' => 'Budgets'],
                    ['route' => 'expenses.index', 'pattern' => 'expenses.*', 'icon' => 'fa-solid fa-receipt', 'label' => 'Expenses'],
                    ['route' => 'debts.index', 'pattern' => 'debts.*', 'icon' => 'fa-solid fa-hand-holding-dollar', 'label' => 'Debts'],
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
                    ['route' => 'wellbeing.index', 'pattern' => 'wellbeing.*', 'icon' => 'fa-solid fa-droplet', 'label' => 'Daily Wellbeing'],
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
                    ['route' => 'daily-planner.index', 'pattern' => 'daily-planner.*', 'icon' => 'fa-solid fa-calendar-check', 'label' => 'Daily Planner'],
                    ['route' => 'annual-plans.index', 'pattern' => 'annual-plans.*', 'icon' => 'fa-solid fa-bullseye', 'label' => 'Annual Plans'],
                    ['route' => 'personal-goals.index', 'pattern' => 'personal-goals.*', 'icon' => 'fa-solid fa-crosshairs', 'label' => 'Goals'],
                    ['route' => 'reminders.index', 'pattern' => 'reminders.*', 'icon' => 'fa-solid fa-bell', 'label' => 'Reminders'],
                    ['route' => 'business-card.edit', 'pattern' => 'business-card.*', 'icon' => 'fa-solid fa-id-card', 'label' => 'My Business Card'],
                    ['route' => 'notes.index', 'pattern' => 'notes.*', 'icon' => 'fa-solid fa-note-sticky', 'label' => 'Notes'],
                    ['route' => 'ai-plans.index', 'pattern' => 'ai-plans.*', 'icon' => 'fa-solid fa-robot', 'label' => 'AI Planner'],
                ],
            ],
            'tools' => [
                'label' => 'Tools & Account',
                'icon' => 'fa-solid fa-toolbox',
                'items' => [
                    ['route' => 'api-credentials.index', 'pattern' => 'api-credentials.*', 'icon' => 'fa-solid fa-key', 'label' => 'API Keys'],
                    ['route' => 'signature.show', 'pattern' => 'signature.*', 'icon' => 'fa-solid fa-signature', 'label' => 'Signatures'],
                    ['route' => 'organization.show', 'pattern' => 'organization.*', 'icon' => 'fa-solid fa-building-user', 'label' => 'Organization'],
                    ['route' => 'feedback.index', 'pattern' => 'feedback.*', 'icon' => 'fa-solid fa-comment-dots', 'label' => 'Feedback'],
                    ['route' => 'tips', 'pattern' => 'tips', 'icon' => 'fa-solid fa-lightbulb', 'label' => 'Usage Tips'],
                ],
            ],
        ];

        if ((string) auth()->user()->role === 'support') {
            $navGroups = [
                'support' => [
                    'label' => 'Support',
                    'icon' => 'fa-solid fa-headset',
                    'items' => [
                        ['route' => 'admin.support.index', 'pattern' => 'admin.support.*', 'icon' => 'fa-solid fa-comments', 'label' => 'Support Conversations'],
                    ],
                ],
            ];
        } elseif (auth()->user()->isAdmin()) {
            $navGroups['admin'] = [
                'label' => 'Admin',
                'icon' => 'fa-solid fa-shield-halved',
                'items' => [
                    ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'icon' => 'fa-solid fa-users', 'label' => 'Users'],
                    ['route' => 'admin.login-activities.index', 'pattern' => 'admin.login-activities.*', 'icon' => 'fa-solid fa-shield-halved', 'label' => 'Login Activities'],
                    ['route' => 'admin.statistics', 'pattern' => 'admin.statistics', 'icon' => 'fa-solid fa-chart-line', 'label' => 'Statistics'],
                    ['route' => 'admin.settings.edit', 'pattern' => 'admin.settings.*', 'icon' => 'fa-solid fa-gear', 'label' => 'Settings'],
                    ['route' => 'admin.payment-gateways.index', 'pattern' => 'admin.payment-gateways.*', 'icon' => 'fa-solid fa-credit-card', 'label' => 'Payment Gateways'],
                    ['route' => 'admin.payments.index', 'pattern' => 'admin.payments.*', 'icon' => 'fa-solid fa-money-check-dollar', 'label' => 'Payments'],
                    ['route' => 'admin.subscription-plans.index', 'pattern' => 'admin.subscription-plans.*', 'icon' => 'fa-solid fa-tags', 'label' => 'Subscription Plans'],
                    ['route' => 'admin.feedback.index', 'pattern' => 'admin.feedback.*', 'icon' => 'fa-solid fa-comment-dots', 'label' => 'Admin Feedback'],
                    ['route' => 'admin.support.index', 'pattern' => 'admin.support.*', 'icon' => 'fa-solid fa-headset', 'label' => 'Support Conversations'],
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

    <aside id="sidebar" class="hidden md:flex md:flex-col md:w-64 md:shrink-0 md:sticky md:top-0 md:h-screen md:self-start bg-gradient-to-b from-[var(--brand-1)] to-[var(--brand-1-dark)]">
        <div class="px-4 py-5 border-b border-white/10">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 font-bold text-lg text-white {{ $isActive('profile.edit') ? 'opacity-80' : '' }}" @if($isActive('profile.edit')) aria-current="page" @endif>
                @if (auth()->user()->avatarUrl())
                    <img src="{{ auth()->user()->avatarUrl() }}" alt="" aria-hidden="true"
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

    <div class="flex-1 min-w-0 w-full">
        <main id="main-content" class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 xl:px-10 py-5 sm:py-6 lg:py-8">
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

        <footer class="pm-layout-footer w-full max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 xl:px-10 py-8 text-center text-xs">
            <a href="{{ route('privacy-policy') }}">Privacy Policy</a>
            <span class="mx-2">&middot;</span>
            <a href="{{ Route::has('terms-of-use') ? route('terms-of-use') : url('/terms-of-use') }}">Terms of Use</a>
        </footer>
    </div>
</div>

@auth
    {{--
        In-app "alarm" for reminders  a real-time layer ON TOP OF (not a
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
            // occurred before an AudioContext can produce AUDIBLE sound 
            // one created outside of a gesture starts in a 'suspended'
            // state and plays silently, with no error thrown. The alarm
            // tone is triggered from an async polling fetch(), not a
            // direct click, so creating-and-immediately-playing-through a
            // brand new context each time it fires (the original
            // approach) silently failed to produce any sound at all.
            //
            // Fix: capture the FIRST interaction anywhere on an
            // authenticated page (virtually guaranteed to happen almost
            // immediately  clicking a link, typing, tapping a button)
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
                    // Web Audio unsupported  the alarm dialog will still
                    // show, just silently, on a browser this old/limited.
                }
            }

            ['click', 'keydown', 'touchstart'].forEach(function (evt) {
                document.addEventListener(evt, pmUnlockAudioContext, { once: true, passive: true });
            });

            function playAlarmTone() {
                // No interaction captured yet this page load (e.g. a tab
                // left open and untouched from before the reminder fired)
                //  try anyway; some browsers still allow it if the SITE
                // (not just this specific page load) was interacted with
                // previously.
                if (!pmAudioCtx) { pmUnlockAudioContext(); }
                if (!pmAudioCtx) { return; }

                try {
                    // A context can also get suspended again on its own
                    // (e.g. some browsers do this for backgrounded tabs) 
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
                    // Audio is a nice-to-have, not essential  fail silently.
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
    by crud/_fields.blade.php  used by all tracking modules' create/edit
    modals and full-page fallbacks. Progressive enhancement: mic buttons
    are rendered hidden and only revealed here if the browser actually
    supports the Web Speech API (notably: not supported in Firefox as of
    this writing). No server round-trip  recognition runs entirely in
    the browser.

    Continuous, not single-utterance: recognition.continuous = true keeps
    listening across pauses in speech rather than stopping after the first
    thing said. It only stops when the user (a) clicks the same mic button
    again (a toggle), (b) clicks anywhere else  another button, another
    field, another mic  or (c) moves focus to a different field, e.g. via
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

        // Clicking the mic that's already listening stops it  a toggle,
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
        // bookkeeping isn't always reliable across that restart  it can
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

    // Stops dictation the moment the user clicks ANYTHING else  another
    // button (Save/Cancel/a different mic), another field, anywhere on
    // the page  matching "continuously until a user stops or clicks in
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
    // Sidebar group dropdowns  server-rendered initial open/closed state
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
        } catch (e) { /* localStorage unavailable  state just won't persist across page loads */ }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.pm-sidebar-chevron').forEach(function (chevron) {
            var groupKey = chevron.dataset.group;
            var stored = null;
            try { stored = localStorage.getItem('pm-sidebar-group-' + groupKey); } catch (e) { /* ignore */ }
            if (stored === null) { return; } // no saved preference  keep the server-rendered (active-page-based) state

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
    // etc.  see the flash message block near the top of <main>) show
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


<script id="pm-motion-runtime">
(() => {
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const visibleObserver = !reduced && 'IntersectionObserver' in window
        ? new IntersectionObserver((entries, obs) => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('pm-motion-visible');
            obs.unobserve(entry.target);
        }), {threshold: .08, rootMargin: '40px 0px'})
        : null;

    const reveal = (el, delay = 0) => {
        if (!el || el.dataset.pmMotionReady) return;
        el.dataset.pmMotionReady = '1';
        if (reduced) { el.classList.add('pm-motion-visible'); return; }
        el.classList.add('pm-motion-enter');
        if (delay) el.style.animationDelay = `${delay}ms`;
        visibleObserver ? visibleObserver.observe(el) : el.classList.add('pm-motion-visible');
    };

    document.querySelectorAll('.quick-card,.stat-card,.pm-stat-card,.dashboard-card,.insight-card').forEach((el, i) => reveal(el, Math.min(i * 55, 330)));
    document.querySelectorAll('canvas,.chart-container,.apexcharts-canvas,.chartjs-render-monitor,.recharts-wrapper').forEach(el => {
        if (visibleObserver) visibleObserver.observe(el); else el.classList.add('pm-motion-visible');
    });

    const numberSelectors = '[data-countup],.stat-value,.pm-stat-value,.metric-value,.summary-value,.dashboard-stat-value';
    const parseNumber = (text) => {
        const match = String(text).match(/-?[\d][\d,]*(?:\.\d+)?/);
        if (!match) return null;
        const raw = match[0];
        const value = Number(raw.replace(/,/g,''));
        if (!Number.isFinite(value)) return null;
        return {raw, value, index: match.index ?? 0, decimals: (raw.split('.')[1] || '').length};
    };
    const animateNumber = (el) => {
        if (reduced || el.dataset.pmCounted) return;
        const original = el.textContent || '';
        const parsed = parseNumber(original);
        if (!parsed) return;
        el.dataset.pmCounted = '1';
        el.classList.add('pm-counting');
        const prefix = original.slice(0, parsed.index);
        const suffix = original.slice(parsed.index + parsed.raw.length);
        const start = performance.now();
        const duration = Math.min(1200, Math.max(520, Math.abs(parsed.value) > 100000 ? 1050 : 760));
        const format = (v) => Number(v).toLocaleString(undefined,{minimumFractionDigits:parsed.decimals,maximumFractionDigits:parsed.decimals});
        const tick = (now) => {
            const t = Math.min(1,(now-start)/duration);
            const eased = 1-Math.pow(1-t,3);
            el.textContent = prefix + format(parsed.value*eased) + suffix;
            if (t < 1) requestAnimationFrame(tick); else el.textContent = original;
        };
        requestAnimationFrame(tick);
    };
    const countObserver = !reduced && 'IntersectionObserver' in window
        ? new IntersectionObserver((entries, obs) => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            animateNumber(entry.target); obs.unobserve(entry.target);
        }), {threshold:.35}) : null;
    document.querySelectorAll(numberSelectors).forEach(el => countObserver ? countObserver.observe(el) : animateNumber(el));
})();
</script>

@auth
<script id="pm-engagement-global-runtime">
(function () {
    'use strict';

    /*
     * Global engagement helper.
     *
     * The backend TrackMeaningfulAction middleware is still the authoritative
     * automatic tracker. This runtime only broadcasts a lightweight event
     * after successful AJAX/fetch saves so dashboard widgets can refresh
     * without coupling each module to the retention UI.
     */
    window.pmEngagement = window.pmEngagement || {
        refresh: function () {
            window.dispatchEvent(new CustomEvent('pm:engagement-refresh'));
        }
    };

    const originalFetch = window.fetch;

    if (originalFetch && !window.__pmEngagementFetchPatched) {
        window.fetch = async function () {
            const response = await originalFetch.apply(this, arguments);

            try {
                const request = arguments[0];
                const options = arguments[1] || {};
                const method = String(options.method || 'GET').toUpperCase();
                const url = typeof request === 'string'
                    ? request
                    : (request && request.url ? request.url : '');

                const meaningfulPath = /(daily-planner|expense|saving|contribution|health|wellbeing|exercise|goal|meeting|spiritual)/i.test(url);

                if (
                    response.ok &&
                    ['POST', 'PUT', 'PATCH'].includes(method) &&
                    meaningfulPath &&
                    !/engagement\/meaningful-action/i.test(url)
                ) {
                    window.pmEngagement.refresh();
                }
            } catch (_) {}

            return response;
        };

        window.__pmEngagementFetchPatched = true;
    }
})();
</script>

    @unless(auth()->user()->isAdmin())
        @include('partials.support-chat-widget')
    @endunless
@endauth

@include('partials.confirm-modal')

<script id="pm-global-modal-position-runtime">
(function () {
    'use strict';

    const desktopQuery = window.matchMedia('(min-width: 641px)');

    function isDesktop() {
        return desktopQuery.matches;
    }

    function forceNativeDialogPosition(dialog) {
        if (!dialog || dialog.tagName !== 'DIALOG' || !dialog.open) {
            return;
        }

        if (!isDesktop()) {
            /*
             * Phone positioning is controlled entirely by modal-responsive.css.
             * Remove any desktop inline properties when crossing to phone width.
             */
            [
                'position', 'top', 'left', 'right', 'bottom',
                'margin', 'transform', 'transform-origin', 'z-index'
            ].forEach(function (property) {
                dialog.style.removeProperty(property);
            });
            return;
        }

        /*
         * Inline !important wins over module/page-specific author CSS.
         * Do not set the inset shorthand because it would overwrite top/left.
         */
        dialog.style.setProperty('position', 'fixed', 'important');
        dialog.style.setProperty('top', '50%', 'important');
        dialog.style.setProperty('left', '50%', 'important');
        dialog.style.setProperty('right', 'auto', 'important');
        dialog.style.setProperty('bottom', 'auto', 'important');
        dialog.style.setProperty('margin', '0', 'important');
        dialog.style.setProperty('transform', 'translate3d(-50%, -50%, 0)', 'important');
        dialog.style.setProperty('transform-origin', '50% 50%', 'important');
        dialog.style.setProperty('z-index', '2147483000', 'important');
    }

    function forceLegacyOverlayPosition(overlay) {
        if (!overlay || !isDesktop()) {
            return;
        }

        const isVisible =
            overlay.classList.contains('show') ||
            overlay.classList.contains('open') ||
            overlay.classList.contains('is-open');

        if (!isVisible) {
            return;
        }

        overlay.style.setProperty('position', 'fixed', 'important');
        overlay.style.setProperty('top', '0', 'important');
        overlay.style.setProperty('right', '0', 'important');
        overlay.style.setProperty('bottom', '0', 'important');
        overlay.style.setProperty('left', '0', 'important');
        overlay.style.setProperty('width', '100vw', 'important');
        overlay.style.setProperty('height', '100dvh', 'important');
        overlay.style.setProperty('display', 'flex', 'important');
        overlay.style.setProperty('align-items', 'center', 'important');
        overlay.style.setProperty('justify-content', 'center', 'important');
        overlay.style.setProperty('padding', '24px', 'important');
        overlay.style.setProperty('overflow', 'auto', 'important');
        overlay.style.setProperty('z-index', '2147482990', 'important');

        const panel = overlay.querySelector(
            ':scope > .modal, :scope > .modal-dialog, :scope > .app-dialog, ' +
            ':scope > .ajax-dialog, :scope > .church-modal-dialog, :scope > .birds-modal-dialog'
        );

        if (panel) {
            panel.style.setProperty('position', 'relative', 'important');
            panel.style.setProperty('top', 'auto', 'important');
            panel.style.setProperty('right', 'auto', 'important');
            panel.style.setProperty('bottom', 'auto', 'important');
            panel.style.setProperty('left', 'auto', 'important');
            panel.style.setProperty('margin', 'auto', 'important');
            panel.style.setProperty('transform', 'none', 'important');
        }
    }

    function normaliseAllOpenModals() {
        document.querySelectorAll('dialog[open]').forEach(forceNativeDialogPosition);

        document.querySelectorAll(
            '.modal-overlay, .app-modal, .ajax-modal, .member-modal-overlay, .church-modal, .birds-modal'
        ).forEach(forceLegacyOverlayPosition);
    }

    /*
     * Patch native methods. This is the most reliable point to correct
     * positioning because it runs every time any module calls showModal()
     * or show(), including Reminder/Notification dialogs.
     */
    if (window.HTMLDialogElement && !HTMLDialogElement.prototype.__pmPositionPatched) {
        const originalShowModal = HTMLDialogElement.prototype.showModal;
        const originalShow = HTMLDialogElement.prototype.show;

        HTMLDialogElement.prototype.showModal = function () {
            const result = originalShowModal.apply(this, arguments);
            forceNativeDialogPosition(this);
            requestAnimationFrame(() => forceNativeDialogPosition(this));
            return result;
        };

        HTMLDialogElement.prototype.show = function () {
            const result = originalShow.apply(this, arguments);
            forceNativeDialogPosition(this);
            requestAnimationFrame(() => forceNativeDialogPosition(this));
            return result;
        };

        Object.defineProperty(HTMLDialogElement.prototype, '__pmPositionPatched', {
            value: true,
            configurable: false,
            enumerable: false,
            writable: false
        });
    }

    const observer = new MutationObserver(function (mutations) {
        let shouldNormalise = false;

        mutations.forEach(function (mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length) {
                shouldNormalise = true;
                return;
            }

            if (
                mutation.type === 'attributes' &&
                ['open', 'class'].includes(mutation.attributeName)
            ) {
                shouldNormalise = true;
            }
        });

        if (shouldNormalise) {
            requestAnimationFrame(normaliseAllOpenModals);
        }
    });

    function start() {
        normaliseAllOpenModals();

        observer.observe(document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['open', 'class']
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }

    desktopQuery.addEventListener
        ? desktopQuery.addEventListener('change', normaliseAllOpenModals)
        : desktopQuery.addListener(normaliseAllOpenModals);

    window.addEventListener('resize', normaliseAllOpenModals);

    /*
     * A few old modules apply positioning in click handlers after showModal().
     * Normalise once more after each click.
     */
    document.addEventListener('click', function () {
        requestAnimationFrame(normaliseAllOpenModals);
    }, true);
})();
</script>


<script id="pm-admin-horizontal-tables-script-20260819">
document.addEventListener('DOMContentLoaded', function () {
    if (!document.body.classList.contains('pm-admin-page')) {
        return;
    }

    document.querySelectorAll('#main-content table').forEach(function (table) {
        /*
         * Leave calendar/layout-only tables alone if an admin page ever uses
         * them. Everything else is treated as a normal data table.
         */
        if (
            table.classList.contains('fc-scrollgrid') ||
            table.closest('.fc') ||
            table.classList.contains('pm-no-admin-horizontal')
        ) {
            return;
        }

        table.classList.add('pm-admin-horizontal-table');

        var currentWrap = table.closest('.pm-admin-table-scroll');

        if (!currentWrap) {
            var parent = table.parentElement;

            if (
                parent &&
                (
                    parent.classList.contains('overflow-x-auto') ||
                    parent.classList.contains('table-responsive') ||
                    parent.classList.contains('apple-table-wrap') ||
                    parent.classList.contains('pm-horizontal-table-wrap')
                )
            ) {
                parent.classList.add('pm-admin-table-scroll');
                currentWrap = parent;
            } else {
                var wrapper = document.createElement('div');
                wrapper.className = 'pm-admin-table-scroll';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
                currentWrap = wrapper;
            }
        }

        if (
            window.matchMedia('(max-width: 767.98px)').matches &&
            currentWrap &&
            !currentWrap.previousElementSibling?.classList.contains(
                'pm-admin-table-swipe-hint'
            )
        ) {
            var hint = document.createElement('div');
            hint.className = 'pm-admin-table-swipe-hint';
            hint.innerHTML =
                '<i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i>' +
                '<span>Swipe sideways to view all columns.</span>';

            currentWrap.parentNode.insertBefore(hint, currentWrap);
        }
    });
});
</script>


{{-- Convert every time/datetime-local control to a consistent 12-hour AM/PM UI. --}}
<script src="{{ asset('js/time-12h.js') }}" defer></script>
@stack('scripts')

<script id="pm-modal-viewport-sync-20260820">
(function () {
    'use strict';

    const root = document.documentElement;

    function syncModalViewport() {
        const viewport = window.visualViewport;
        const height = viewport && viewport.height ? viewport.height : window.innerHeight;
        if (height > 0) {
            root.style.setProperty('--pm-modal-vh', `${Math.round(height)}px`);
        }
    }

    function markModalState() {
        const hasNative = !!document.querySelector('dialog[open]');
        const hasLegacy = !!document.querySelector(
            '.modal-overlay.show, .modal-overlay.open, .modal-overlay.is-open,' +
            '.app-modal.show, .app-modal.open, .app-modal.is-open,' +
            '.ajax-modal.show, .ajax-modal.open, .ajax-modal.is-open,' +
            '.member-modal-overlay.show, .member-modal-overlay.open, .member-modal-overlay.is-open,' +
            '.church-modal.show, .church-modal.open, .church-modal.is-open,' +
            '.birds-modal.show, .birds-modal.open, .birds-modal.is-open,' +
            '.pm-component-modal-shell[style*="display: block"]'
        );

        document.documentElement.classList.toggle('pm-modal-open', hasNative || hasLegacy);
        document.body.classList.toggle('pm-modal-open', hasNative || hasLegacy);
    }

    syncModalViewport();
    window.addEventListener('resize', syncModalViewport, { passive: true });
    window.addEventListener('orientationchange', syncModalViewport, { passive: true });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncModalViewport, { passive: true });
        window.visualViewport.addEventListener('scroll', syncModalViewport, { passive: true });
    }

    const observer = new MutationObserver(() => {
        syncModalViewport();
        markModalState();
    });

    observer.observe(document.body, {
        subtree: true,
        attributes: true,
        attributeFilter: ['open', 'class', 'style']
    });

    document.addEventListener('focusin', (event) => {
        if (event.target.closest('dialog, .modal-overlay, .app-modal, .ajax-modal, .member-modal-overlay, .church-modal, .birds-modal, .pm-component-modal-shell')) {
            setTimeout(syncModalViewport, 60);
            setTimeout(syncModalViewport, 260);
        }
    });
})();
</script>

</body>
</html>
