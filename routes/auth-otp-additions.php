<?php

/*
|--------------------------------------------------------------------------
| OTP Login Routes — ADD these into your existing routes/auth.php
|--------------------------------------------------------------------------
|
| Breeze's routes/auth.php already defines a 'guest' middleware group
| containing register/login/password-reset routes, and this file's
| AuthenticatedSessionController@store now redirects into the OTP flow
| instead of logging the user in directly. Add the three lines below
| inside that SAME `Route::middleware('guest')->group(function () { ... })`
| block, right after the existing login routes:
|
|   Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
|   Route::post('login', [AuthenticatedSessionController::class, 'store']);
|
|   Route::get('login/otp', [OtpVerificationController::class, 'show'])->name('otp.verify');
|   Route::post('login/otp', [OtpVerificationController::class, 'verify'])->name('otp.verify.submit');
|   Route::post('login/otp/resend', [OtpVerificationController::class, 'resend'])->name('otp.resend');
|   Route::post('login/otp/cancel', [OtpVerificationController::class, 'cancel'])->name('otp.cancel');
|
| Also add this import near the top of routes/auth.php, alongside the
| existing AuthenticatedSessionController import:
|
|   use App\Http\Controllers\Auth\OtpVerificationController;
|
| Nothing else in routes/auth.php needs to change — register, password
| reset, and email verification routes are untouched by this feature.
|
*/
