<?php

use App\Http\Controllers\Api\AdminSocialMediaController;
use App\Http\Controllers\Api\SocialMediaAccountController;

/*
Put these inside the existing auth:sanctum + mobile.idempotent API group.
*/

Route::get(
    'profile/social-media',
    [SocialMediaAccountController::class, 'index']
);

Route::put(
    'profile/social-media/whatsapp',
    [SocialMediaAccountController::class, 'updateWhatsApp']
);

Route::post(
    'profile/social-media/accounts',
    [SocialMediaAccountController::class, 'store']
);

Route::delete(
    'profile/social-media/accounts/{account}',
    [SocialMediaAccountController::class, 'destroy']
)->whereNumber('account');

Route::get(
    'admin/social-media',
    [AdminSocialMediaController::class, 'index']
);

Route::put(
    'admin/social-media/users/{user}/whatsapp',
    [AdminSocialMediaController::class, 'updateWhatsApp']
)->whereNumber('user');

Route::delete(
    'admin/social-media/users/{user}/accounts/{account}',
    [AdminSocialMediaController::class, 'destroyAccount']
)
    ->whereNumber('user')
    ->whereNumber('account');
