<?php

// Add these imports to routes/web.php:
use App\Http\Controllers\SocialMediaAccountController;
use App\Http\Controllers\SocialMediaPlannerController;

// Inside the existing auth + verified + subscribed group:
Route::prefix('social-media-planner')
    ->name('social-media-planner.')
    ->group(function () {
        Route::get('/', [SocialMediaPlannerController::class, 'index'])->name('index');
        Route::post('/', [SocialMediaPlannerController::class, 'store'])->name('store');
        Route::delete('/bulk-destroy', [SocialMediaPlannerController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::get('/ready-to-share/list', [SocialMediaPlannerController::class, 'readyToShare'])->name('ready-to-share');
        Route::put('/{socialMediaPost}', [SocialMediaPlannerController::class, 'update'])->whereNumber('socialMediaPost')->name('update');
        Route::delete('/{socialMediaPost}', [SocialMediaPlannerController::class, 'destroy'])->whereNumber('socialMediaPost')->name('destroy');
    });

// Inside the normal auth group/profile area:
Route::get('/profile/social-media', [SocialMediaAccountController::class, 'index'])->name('profile.social-media');
Route::post('/profile/social-media/accounts', [SocialMediaAccountController::class, 'store'])->name('profile.social-media.accounts.store');
Route::delete('/profile/social-media/accounts/{account}', [SocialMediaAccountController::class, 'destroy'])->name('profile.social-media.accounts.destroy');
Route::put('/profile/social-media/whatsapp', [SocialMediaAccountController::class, 'updateWhatsApp'])->name('profile.social-media.whatsapp');

// Add this import to routes/api.php:
use App\Http\Controllers\Api\SocialMediaAccountController as ApiSocialMediaAccountController;
use App\Http\Controllers\Api\SocialMediaPlannerController;

// Inside existing auth:sanctum group:
Route::post('social-media-planner/bulk-delete', [SocialMediaPlannerController::class, 'bulkDestroy']);
Route::get('profile/social-media', [ApiSocialMediaAccountController::class, 'index']);
Route::post('profile/social-media/accounts', [ApiSocialMediaAccountController::class, 'store']);
Route::delete('profile/social-media/accounts/{account}', [ApiSocialMediaAccountController::class, 'destroy']);
Route::put('profile/social-media/whatsapp', [ApiSocialMediaAccountController::class, 'updateWhatsApp']);
