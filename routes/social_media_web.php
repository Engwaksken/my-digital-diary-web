<?php

use App\Http\Controllers\SocialMediaPlannerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','verified','subscribed'])
    ->prefix('social-media-planner')
    ->name('social-media-planner.')
    ->group(function () {
        Route::get('/', [SocialMediaPlannerController::class, 'index'])->name('index');
        Route::post('/', [SocialMediaPlannerController::class, 'store'])->name('store');
    });
