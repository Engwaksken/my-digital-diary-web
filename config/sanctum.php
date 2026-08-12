<?php

use Laravel\Sanctum\Sanctum;

/**
 * Standard Laravel Sanctum config, written by hand here since this
 * sandbox has no network access to actually run
 * `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`.
 * Once you run `composer require laravel/sanctum` locally, this file
 * matches exactly what that publish command would generate — copy it in
 * directly rather than publishing and re-editing.
 *
 * For the Flutter app, only 'guard' and the token abilities matter — the
 * mobile app authenticates with a plain Bearer token (Sanctum's
 * "API token" mode), NOT the stateful/SPA cookie mode 'stateful' below
 * governs, so you do not need to add your Flutter app's origin there.
 * 'stateful' is only relevant if you later build a browser-based SPA
 * against this same API.
 */
return [
    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'
    )),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
