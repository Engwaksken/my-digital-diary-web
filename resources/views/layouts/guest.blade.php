<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'My Digital Diary'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/professional-forms.css') }}">

    <style>
        :root {
            --brand-1: #00897B;
            --brand-1-dark: #006B60;
            --brand-2: #73BEB6;
            --auth-bg: #f5f8f7;
            --auth-text: #172033;
            --auth-muted: #667085;
            --auth-line: rgba(15,23,42,.09);
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            color: var(--auth-text);
            background:
                radial-gradient(circle at 15% 10%, rgba(0,137,123,.13), transparent 31%),
                radial-gradient(circle at 90% 90%, rgba(115,190,182,.14), transparent 32%),
                var(--auth-bg);
        }
        h1, h2, h3, h4, h5, h6 { font-family: 'Outfit', 'Poppins', sans-serif; }
        .pm-guest-shell {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 28px 16px;
        }
        .pm-guest-wrap { width: min(100%, 470px); }
        .pm-guest-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            text-align: center;
            text-decoration: none;
        }
        .pm-guest-logo {
            width: 84px;
            height: 84px;
            display: grid;
            place-items: center;
            border-radius: 26px;
            background: rgba(255,255,255,.94);
            border: 1px solid rgba(255,255,255,.9);
            box-shadow: 0 14px 38px rgba(15,23,42,.10);
        }
        .pm-guest-logo svg, .pm-guest-logo img { max-width: 58px; max-height: 58px; }
        .pm-guest-brand-name {
            font: 700 1.12rem/1.2 'Outfit', sans-serif;
            color: #18202d;
            letter-spacing: -.02em;
        }
        .pm-guest-card {
            width: 100%;
            padding: clamp(22px, 5vw, 34px);
            border-radius: 24px;
            background: rgba(255,255,255,.94);
            border: 1px solid rgba(255,255,255,.9);
            box-shadow: 0 20px 55px rgba(15,23,42,.10);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }
        .pm-guest-footer {
            padding-top: 18px;
            color: var(--auth-muted);
            text-align: center;
            font-size: .76rem;
        }
        .pm-guest-footer a { color: var(--brand-1); font-weight: 600; text-decoration: none; }
        @media (max-width: 480px) {
            .pm-guest-shell { padding: 18px 12px; place-items: start center; }
            .pm-guest-wrap { margin-top: 2vh; }
            .pm-guest-card { padding: 20px 16px; border-radius: 20px; }
            .pm-guest-logo { width: 72px; height: 72px; border-radius: 22px; }
        }
    </style>
    @stack('styles')
</head>
<body class="antialiased">
    @include('partials.password-toggle')

    <main class="pm-guest-shell">
        <div class="pm-guest-wrap">
            <a href="{{ url('/') }}" class="pm-guest-brand" aria-label="{{ config('app.name', 'My Digital Diary') }} home">
                <span class="pm-guest-logo">
                    <x-application-logo class="w-14 h-14 fill-current text-[var(--brand-1)]" />
                </span>
                <span class="pm-guest-brand-name">{{ config('app.name', 'My Digital Diary') }}</span>
            </a>

            <section class="pm-guest-card">
                {{ $slot }}
            </section>

            <footer class="pm-guest-footer">
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'My Digital Diary') }}.</span>
                @if (Route::has('privacy-policy'))
                    <span> &middot; </span><a href="{{ route('privacy-policy') }}">Privacy</a>
                @endif
                <span> &middot; </span><a href="{{ Route::has('terms-of-use') ? route('terms-of-use') : url('/terms-of-use') }}">Terms</a>
            </footer>
        </div>
    </main>

    @stack('scripts')
</body>
</html>
