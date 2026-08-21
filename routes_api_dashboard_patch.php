<?php
/*
|--------------------------------------------------------------------------
| routes/api.php — replace ONLY the existing Dashboard route lines
|--------------------------------------------------------------------------
|
| Inside your existing authenticated API group, use:
|
*/

Route::get('dashboard', [\App\Http\Controllers\Api\MobileDashboardController::class, 'index']);

Route::get('dashboard/today-focus', [
    \App\Http\Controllers\Api\MobileDashboardController::class,
    'todayFocus',
]);

/*
| Keep your existing finance-summary, recent-activity, today-insight and all
| other API routes exactly as they are.
*/
