<?php

use App\Http\Controllers\GrowthStrategyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Growth & Referral — Web
|--------------------------------------------------------------------------
*/

Route::get('/join/{code}', [GrowthStrategyController::class, 'invite'])
    ->where('code', '[A-Za-z0-9_-]+')
    ->name('growth.invite');

Route::middleware(['auth', 'verified', 'subscribed'])
    ->prefix('growth')
    ->name('growth.')
    ->group(function (): void {
        Route::get('/dashboard', [GrowthStrategyController::class, 'dashboard'])
            ->name('dashboard');

        Route::post('/challenge/join', [GrowthStrategyController::class, 'joinChallenge'])
            ->name('challenge.join');

        Route::post('/referral', [GrowthStrategyController::class, 'referral'])
            ->name('referral');

        Route::get('/preferences', [GrowthStrategyController::class, 'preferences'])
            ->name('preferences');

        Route::put('/preferences', [GrowthStrategyController::class, 'updatePreferences'])
            ->name('preferences.update');

        Route::post('/track', [GrowthStrategyController::class, 'track'])
            ->name('track');
    });
