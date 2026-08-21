<?php

use App\Http\Controllers\Api\EngagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('engagement')
    ->name('engagement.')
    ->group(function () {
        Route::get('/today', [EngagementController::class, 'today'])
            ->name('today');

        Route::post('/checkin/start-day', [EngagementController::class, 'checkin'])
            ->defaults('type', 'start-day')
            ->name('checkin.start-day');

        Route::post('/checkin/close-day', [EngagementController::class, 'checkin'])
            ->defaults('type', 'close-day')
            ->name('checkin.close-day');

        Route::post('/meaningful-action', [EngagementController::class, 'meaningfulAction'])
            ->name('meaningful-action');

        Route::get('/review/week', [EngagementController::class, 'weekly'])
            ->name('review.week');

        Route::get('/review/month', [EngagementController::class, 'monthly'])
            ->name('review.month');

        Route::get('/share-card/week', [EngagementController::class, 'shareCard'])
            ->defaults('period', 'week')
            ->name('share-card.week');

        Route::get('/share-card/month', [EngagementController::class, 'shareCard'])
            ->defaults('period', 'month')
            ->name('share-card.month');
    });