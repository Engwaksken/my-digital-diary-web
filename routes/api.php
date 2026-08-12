<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DietLogController;
use App\Http\Controllers\Api\EducationPlanController;
use App\Http\Controllers\Api\ExerciseLogController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\HealthCheckupController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\NetworkContactController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTaskController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SavingsContributionController;
use App\Http\Controllers\Api\SavingsGoalController;
use App\Http\Controllers\Api\SleepLogController;
use App\Http\Controllers\Api\SpiritualPracticeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Flutter mobile app)
|--------------------------------------------------------------------------
|
| ADD this require to bootstrap/app.php's routing config, same pattern as
| routes/admin.php — see the withRouting() closure:
|
|   ->withRouting(
|       web: __DIR__.'/../routes/web.php',
|       api: __DIR__.'/../routes/api.php',   <-- add this line
|       commands: __DIR__.'/../routes/console.php',
|       health: '/up',
|   )
|
| Every route below except register/login/verify-otp/resend-otp requires
| a Sanctum Bearer token (Authorization: Bearer {token}), obtained from
| POST /api/verify-otp. There is no CSRF/session concern here at all —
| unlike the web app's cookie-based auth, this is stateless token auth,
| the same mechanism a Flutter HTTP client naturally works with.
*/

Route::get('branding', [\App\Http\Controllers\Api\BrandingController::class, 'show']);
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('resend-otp', [AuthController::class, 'resendOtp']);

// Every route name in this group gets an 'api.' prefix — Route::apiResource()
// auto-generates names (plans.index, incomes.index, etc.) using the exact
// same convention as the web app's own Route::resource() calls, which
// caused a real, wide-reaching bug: route('plans.index') in the sidebar
// was resolving to this API route instead of the web page, since two
// routes shared the same name and Laravel's route() helper just picks
// whichever one it finds. This prefix makes every name here unique,
// fixing that collision for all ~20 affected modules at once and
// preventing it from ever recurring for any route added to this group
// in the future.
Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::get('me', [AuthController::class, 'me']);

    Route::get('api-credentials', [\App\Http\Controllers\Api\ApiCredentialController::class, 'index']);
    Route::post('api-credentials', [\App\Http\Controllers\Api\ApiCredentialController::class, 'store']);
    Route::post('api-credentials/{apiCredential}/activate', [\App\Http\Controllers\Api\ApiCredentialController::class, 'activate']);
    Route::delete('api-credentials/{apiCredential}', [\App\Http\Controllers\Api\ApiCredentialController::class, 'destroy']);
    Route::get('search', [\App\Http\Controllers\Api\SearchController::class, 'search']);
    Route::post('profile/appearance', [\App\Http\Controllers\Api\ProfileController::class, 'updateAppearance']);
    Route::put('profile', [\App\Http\Controllers\Api\ProfileController::class, 'updateProfile']);
    Route::put('profile/password', [\App\Http\Controllers\Api\ProfileController::class, 'updatePassword']);
    Route::post('profile/avatar', [\App\Http\Controllers\Api\ProfileController::class, 'updateAvatar']);
    Route::get('profile/avatar/image', [\App\Http\Controllers\Api\ProfileController::class, 'avatarImage']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::post('device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('device-tokens', [DeviceTokenController::class, 'destroy']);

    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/recent-activity', [DashboardController::class, 'recentActivityFull']);


    // Financial Planner + Daily Planner mobile endpoints
    Route::get('financial-planner', [\App\Http\Controllers\Api\FinancialPlannerController::class, 'index']);
    Route::put('financial-planner', [\App\Http\Controllers\Api\FinancialPlannerController::class, 'update']);
    Route::get('daily-planner', [\App\Http\Controllers\Api\DailyPlannerController::class, 'index']);
    Route::get('daily-planner/history', [\App\Http\Controllers\Api\DailyPlannerController::class, 'history']);
    Route::put('daily-planner', [\App\Http\Controllers\Api\DailyPlannerController::class, 'updatePlan']);
    Route::post('daily-planner/items', [\App\Http\Controllers\Api\DailyPlannerController::class, 'storeItem']);
    Route::put('daily-planner/items/{item}', [\App\Http\Controllers\Api\DailyPlannerController::class, 'updateItem']);
    Route::patch('daily-planner/items/{item}/toggle', [\App\Http\Controllers\Api\DailyPlannerController::class, 'toggle']);
    Route::delete('daily-planner/items/{item}', [\App\Http\Controllers\Api\DailyPlannerController::class, 'destroyItem']);


    // Annual Plans mobile endpoints
    Route::get('annual-plans', [\App\Http\Controllers\Api\AnnualPlanController::class, 'index']);
    Route::post('annual-plans', [\App\Http\Controllers\Api\AnnualPlanController::class, 'store']);
    Route::put('annual-plans/{annualPlan}', [\App\Http\Controllers\Api\AnnualPlanController::class, 'update']);
    Route::patch('annual-plans/{annualPlan}/toggle', [\App\Http\Controllers\Api\AnnualPlanController::class, 'toggle']);
    Route::delete('annual-plans/{annualPlan}', [\App\Http\Controllers\Api\AnnualPlanController::class, 'destroy']);

    // Full module set — every web CRUD module now has a matching JSON
    // endpoint here, following the exact same ApiCrudController pattern.
    Route::get('reminders/due-now', [\App\Http\Controllers\Api\ReminderController::class, 'dueNow']);
    Route::post('reminders/toggle-mute', [\App\Http\Controllers\Api\ReminderController::class, 'toggleMute']);
    Route::get('reminders/items-for-module', [\App\Http\Controllers\Api\ReminderController::class, 'itemsForModule']);
    // archive/unarchive routes are registered BEFORE each apiResource
    // for the same reason reminders/due-now had to be registered
    // before Route::apiResource('reminders', ...) earlier — a
    // {module}/{id} wildcard route would otherwise treat "archive" as
    // if it were an {id} value.
    foreach ([
        'reminders' => ReminderController::class,
        'meetings' => MeetingController::class,
        'expenses' => ExpenseController::class,
        'plans' => PlanController::class,
        'incomes' => IncomeController::class,
        'budgets' => BudgetController::class,
        'debts' => DebtController::class,
        'savings-goals' => SavingsGoalController::class,
        'savings-contributions' => SavingsContributionController::class,
        'diet-logs' => DietLogController::class,
        'exercise-logs' => ExerciseLogController::class,
        'sleep-logs' => SleepLogController::class,
        'health-checkups' => HealthCheckupController::class,
        'projects' => ProjectController::class,
        'project-tasks' => ProjectTaskController::class,
        'education-plans' => EducationPlanController::class,
        'network-contacts' => NetworkContactController::class,
        'relationships' => RelationshipController::class,
        'spiritual-practices' => SpiritualPracticeController::class,
        'feedback' => FeedbackController::class,
    ] as $endpoint => $controller) {
        Route::post("{$endpoint}/{id}/archive", [$controller, 'archive'])->where('id', '[0-9]+');
        Route::post("{$endpoint}/{id}/unarchive", [$controller, 'unarchive'])->where('id', '[0-9]+');
        Route::get("{$endpoint}/stats", [$controller, 'stats']);
        Route::get("{$endpoint}/report/pdf", [$controller, 'downloadPdf']);
        Route::apiResource($endpoint, $controller);
    }

    // Business Card
    Route::get('business-card', [\App\Http\Controllers\Api\BusinessCardController::class, 'show']);
    Route::get('business-card/photo', [\App\Http\Controllers\Api\BusinessCardController::class, 'photo']);
    Route::post('business-card', [\App\Http\Controllers\Api\BusinessCardController::class, 'update']);
    Route::post('business-card/toggle-published', [\App\Http\Controllers\Api\BusinessCardController::class, 'togglePublished']);
    Route::get('business-card/pdf', [\App\Http\Controllers\Api\BusinessCardController::class, 'downloadPdf']);

    // Signatures — view/download only on mobile, no editor
    Route::get('signatures', [\App\Http\Controllers\Api\SignatureController::class, 'signatures']);
    Route::post('signatures', [\App\Http\Controllers\Api\SignatureController::class, 'storeSignature']);
    Route::get('signatures/{signature}/image', [\App\Http\Controllers\Api\SignatureController::class, 'image']);
    Route::delete('signatures/{signature}', [\App\Http\Controllers\Api\SignatureController::class, 'destroySignature']);
    Route::get('signed-documents', [\App\Http\Controllers\Api\SignatureController::class, 'documents']);
    Route::delete('signed-documents/{signedDocument}', [\App\Http\Controllers\Api\SignatureController::class, 'destroyDocument']);
    Route::post('signed-documents/stamp-image', [\App\Http\Controllers\Api\SignatureController::class, 'stampImage']);
    Route::post('signed-documents/bulk-delete', [\App\Http\Controllers\Api\SignatureController::class, 'bulkDestroyDocuments']);

    // Subscription / billing
    Route::get('subscription/status', [\App\Http\Controllers\Api\SubscriptionController::class, 'status']);
    Route::get('subscription/plans', [\App\Http\Controllers\Api\SubscriptionController::class, 'plans']);
    Route::get('subscription/gateways', [\App\Http\Controllers\Api\SubscriptionController::class, 'gateways']);
    Route::get('subscription/payments', [\App\Http\Controllers\Api\SubscriptionController::class, 'payments']);
    Route::post('subscription/pay/card', [\App\Http\Controllers\Api\SubscriptionController::class, 'payWithCard']);
    Route::post('subscription/pay/mobile-money', [\App\Http\Controllers\Api\SubscriptionController::class, 'payWithMobileMoney']);
    Route::post('subscription/pay/manual', [\App\Http\Controllers\Api\SubscriptionController::class, 'submitManualPayment']);

    // Meeting recordings
    Route::get('meetings/{meeting}/recordings', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'index']);
    Route::post('meetings/{meeting}/recordings', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'store']);
    Route::patch('meeting-recordings/{recording}/status', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'updateStatus']);
    Route::post('meeting-recordings/{recording}/stop', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'stop']);
    Route::post('meeting-recordings/{recording}/transcribe', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'transcribe']);
    Route::put('meeting-recordings/{recording}/transcript', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'updateTranscript']);
    Route::post('meeting-recordings/{recording}/summarize', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'generateSummary']);
    Route::delete('meeting-recordings/{recording}', [\App\Http\Controllers\Api\MeetingRecordingController::class, 'destroy']);

    // Organization / Team management
    Route::get('organization', [\App\Http\Controllers\Api\OrganizationController::class, 'show']);
    Route::post('organization/invite', [\App\Http\Controllers\Api\OrganizationController::class, 'invite']);
    Route::post('organization/members/{member}/activate', [\App\Http\Controllers\Api\OrganizationController::class, 'activate']);
    Route::post('organization/members/{member}/deactivate', [\App\Http\Controllers\Api\OrganizationController::class, 'deactivate']);
    Route::post('organization/members/{member}/replace', [\App\Http\Controllers\Api\OrganizationController::class, 'replace']);
    Route::delete('organization/members/{member}', [\App\Http\Controllers\Api\OrganizationController::class, 'removeMember']);

    // Notifications
    Route::get('notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);

    // AI Planner
    Route::get('ai-plans', [\App\Http\Controllers\Api\AiPlanController::class, 'index']);
    Route::post('ai-plans', [\App\Http\Controllers\Api\AiPlanController::class, 'store']);
    Route::delete('ai-plans/{aiPlan}', [\App\Http\Controllers\Api\AiPlanController::class, 'destroy']);
});
