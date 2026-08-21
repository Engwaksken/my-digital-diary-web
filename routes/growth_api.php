<?php
use App\Http\Controllers\GrowthStrategyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('growth')->group(function () {
    Route::get('/dashboard', [GrowthStrategyController::class, 'dashboard']);
    Route::post('/challenge/join', [GrowthStrategyController::class, 'joinChallenge']);
    Route::post('/referral', [GrowthStrategyController::class, 'referral']);
    Route::get('/preferences', [GrowthStrategyController::class, 'preferences']);
    Route::put('/preferences', [GrowthStrategyController::class, 'updatePreferences']);
    Route::post('/track', [GrowthStrategyController::class, 'track']);
});
