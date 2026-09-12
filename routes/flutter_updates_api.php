<?php

declare(strict_types=1);

use App\Http\Controllers\AiFormAssistController;
use App\Http\Controllers\SocialMediaPlannerController;
use App\Http\Controllers\Api\EngagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    // Flutter AI-assisted forms, including Spiritual Growth.
    Route::post('ai/form-assist', [AiFormAssistController::class, 'generate']);

    // Compatibility aliases used by Flutter Start Day / Close Day.
    Route::post('daily-routine/start-day', function (\Illuminate\Http\Request $request, EngagementController $controller) {
        return $controller->checkin($request, 'start-day');
    });
    Route::post('daily-routine/close-day', function (\Illuminate\Http\Request $request, EngagementController $controller) {
        return $controller->checkin($request, 'close-day');
    });

    // Flutter Social Media Planner routes. whereNumber protects static routes
    // from ever being interpreted as a post ID.
    Route::get('social-media-planner', [SocialMediaPlannerController::class, 'index']);
    Route::post('social-media-planner', [SocialMediaPlannerController::class, 'store']);
    Route::get('social-media-planner/ready-to-share', [SocialMediaPlannerController::class, 'readyToShare']);
    Route::post('social-media-planner/{socialMediaPost}/post-now', [SocialMediaPlannerController::class, 'postNow'])
        ->whereNumber('socialMediaPost');
    Route::put('social-media-planner/{socialMediaPost}', [SocialMediaPlannerController::class, 'update'])
        ->whereNumber('socialMediaPost');
    Route::delete('social-media-planner/{socialMediaPost}', [SocialMediaPlannerController::class, 'destroy'])
        ->whereNumber('socialMediaPost');
});
