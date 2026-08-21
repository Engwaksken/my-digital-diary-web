<?php

/*
|--------------------------------------------------------------------------
| Engagement review route replacements
|--------------------------------------------------------------------------
|
| The existing EngagementController review routes are returning zero period
| metrics. Replace ONLY the week/month routes; keep today/checkin/share-card
| on the existing controller.
|
| Add these imports:
|
| use App\Http\Controllers\EngagementReviewController;
| use App\Http\Controllers\Api\EngagementReviewController as ApiEngagementReviewController;
|
*/

// routes/web.php — inside auth + verified + subscribed:
Route::get('/engagement/review/week', [EngagementReviewController::class, 'week'])
    ->name('engagement.review.week');

Route::get('/engagement/review/month', [EngagementReviewController::class, 'month'])
    ->name('engagement.review.month');

// routes/api.php — inside auth:sanctum + mobile.idempotent:
Route::get('engagement/review/week', [ApiEngagementReviewController::class, 'week']);

Route::get('engagement/review/month', [ApiEngagementReviewController::class, 'month']);

/*
IMPORTANT:
Remove/comment the old week/month definitions first so the route list does
not contain duplicate URIs/names.
*/
