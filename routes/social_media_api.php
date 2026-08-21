<?php

use App\Http\Controllers\Api\SocialMediaPlannerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('social-media-planner')
    ->group(function () {
        Route::get('/', [SocialMediaPlannerController::class, 'index']);
        Route::post('/', [SocialMediaPlannerController::class, 'store']);
        Route::put('/{socialMediaPost}', [SocialMediaPlannerController::class, 'update']);
        Route::delete('/{socialMediaPost}', [SocialMediaPlannerController::class, 'destroy']);
        Route::get('/ready-to-share', [SocialMediaPlannerController::class, 'readyToShare']);
    });
