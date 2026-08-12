<?php

use App\Http\Controllers\AiPlanController;
use App\Http\Controllers\ApiCredentialController;
use App\Http\Controllers\BusinessCardController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\DailyPlannerController;
use App\Http\Controllers\FinancialPlannerController;
use App\Http\Controllers\DietLogController;
use App\Http\Controllers\ExerciseLogController;
use App\Http\Controllers\EducationPlanController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\TipsController;
use App\Http\Controllers\HealthCheckupController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingConnectionController;
use App\Http\Controllers\MeetingRecordingController;
use App\Http\Controllers\NetworkContactController;
use App\Http\Controllers\PersonalRelationshipController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\PrivacyPolicyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsContributionController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\SleepLogController;
use App\Http\Controllers\SpiritualPracticeController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file assumes you already have Laravel's default auth scaffolding
| (e.g. via `laravel/breeze`) providing /login, /register, /logout and the
| "auth" middleware, plus the 'subscribed' and 'admin' middleware aliases
| registered in bootstrap/app.php (see README.md).
|
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public — reachable without an account, since it's linked from the
// registration consent checkbox.
Route::get('/privacy-policy', [PrivacyPolicyController::class, 'show'])->name('privacy-policy');

// Public — whoever scans a user's business-card QR code or opens their
// shared link lands here with no account needed, same reasoning as
// privacy-policy above.
Route::get('/card/{slug}', [BusinessCardController::class, 'showPublic'])->name('card.show');

Route::get('/signed-documents/{signedDocument}/shared', [\App\Http\Controllers\SignatureController::class, 'showSharedDocument'])
    ->middleware('signed')
    ->name('signature.documents.shared');

// Public — a prospect considering Enterprise may not have an account yet;
// an existing user can also reach this to inquire about upgrading.
Route::get('/enterprise/contact', [\App\Http\Controllers\EnterpriseInquiryController::class, 'show'])->name('enterprise.contact');
Route::post('/enterprise/contact', [\App\Http\Controllers\EnterpriseInquiryController::class, 'store'])->name('enterprise.contact.submit');

// Public — reached from an emailed invite link before the recipient
// necessarily has an account; handles both cases itself (see
// OrganizationController::acceptInvite()).
Route::get('/organization/accept-invite/{token}', [\App\Http\Controllers\OrganizationController::class, 'acceptInvite'])->name('organization.accept-invite');

// Public — Stripe redirects the BROWSER back here after checkout, which
// has no session at all if checkout was started from the mobile app (an
// API request, with the checkout URL opened in an external browser).
// Safe without auth because it's looked up by Stripe's own unique,
// unguessable session id, never by "whoever is currently logged in."
Route::get('/subscription/pay/card/callback', [SubscriptionController::class, 'payWithCardCallback'])->name('subscription.pay.card.callback');

// Public — IoTec's own servers call this directly to report a
// collection's final status. IMPORTANT: this route must be added to the
// CSRF exception list in bootstrap/app.php (withMiddleware ->
// validateCsrfTokens(except: [...])) — I don't have that file in front
// of me to edit safely (it's never been touched before in this project),
// so without that one line added manually, every webhook call from
// IoTec will get rejected with a 419 before it ever reaches the
// controller. See the deployment notes for the exact line to add.
//
// This now routes through the generic PaymentGatewayWebhookController
// (see app/PaymentGateways/) rather than the IoTec-specific one, so the
// SAME webhook handling logic works for any future aggregator too — the
// URL stays /webhooks/iotec (already registered on IoTec's side) but
// resolves gateway_code='iotec' internally rather than needing its own
// dedicated controller.
Route::post('/webhooks/iotec', [\App\Http\Controllers\PaymentGatewayWebhookController::class, 'handle'])
    ->name('webhooks.iotec')
    ->defaults('gatewayCode', 'iotec');

// Generic — for any FUTURE aggregator, register
// https://yourdomain/webhooks/{gateway_code} on their side (matching
// whatever gateway_code you set in Admin -> Payment Gateways) instead of
// needing a new named route added here each time.
Route::post('/webhooks/{gatewayCode}', [\App\Http\Controllers\PaymentGatewayWebhookController::class, 'handle'])
    ->name('webhooks.gateway');

// Subscription/billing + profile routes are intentionally OUTSIDE the
// 'subscribed' gate below — a user whose trial has lapsed still needs to
// be able to reach these to fix billing or update their own account.
//
// NOTE: /profile/* here are Breeze's own routes. Because this file
// REPLACES the web.php that `breeze:install` generates (which normally
// defines these itself), they have to be restored explicitly — the same
// issue as the 'login' route fixed earlier. Skipping this reintroduces a
// RouteNotFoundException the moment anything links to route('profile.edit').
Route::middleware(['auth'])->group(function () {
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::post('/subscription/pay/card', [SubscriptionController::class, 'payWithCard'])->name('subscription.pay.card');
    Route::post('/subscription/pay/mobile-money', [SubscriptionController::class, 'payWithMobileMoney'])->name('subscription.pay.mobile-money');
    Route::get('/subscription/receipt/{payment}', [SubscriptionController::class, 'downloadReceipt'])->name('subscription.receipt');
    Route::get('/subscription/invoice/{invoice}', [SubscriptionController::class, 'downloadInvoice'])->name('subscription.invoice');
    Route::delete('/subscription/invoices/bulk-destroy', [SubscriptionController::class, 'bulkDestroyInvoices'])->name('subscription.invoices.bulk-destroy');
    Route::delete('/subscription/receipts/bulk-destroy', [SubscriptionController::class, 'bulkDestroyReceipts'])->name('subscription.receipts.bulk-destroy');
    Route::post('/subscription/pay/manual', [SubscriptionController::class, 'submitManualPayment'])->name('subscription.pay.manual');

    // Same reasoning: data export/deletion are rights a user should be
    // able to exercise even if their trial/subscription has lapsed.
    Route::get('/privacy', [PrivacyController::class, 'show'])->name('privacy.show');
    Route::get('/help', [\App\Http\Controllers\HelpController::class, 'show'])->name('help.show');
    Route::post('/privacy/export/request', [PrivacyController::class, 'requestExport'])->name('privacy.export.request');
    Route::get('/privacy/export/download', [PrivacyController::class, 'downloadExport'])->name('privacy.export.download');
    Route::delete('/privacy/account', [PrivacyController::class, 'destroyAccount'])->name('privacy.destroy-account');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::put('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
});

Route::middleware(['auth', 'verified', 'subscribed'])->group(function () {
    Route::get('/dashboard', DashboardController::class . '@index')->name('dashboard');
    Route::get('/activity', fn () => redirect()->route('annual-plans.index'))->name('activity');

    // Bulk-delete routes must be registered before Route::resource() so
    // the literal "bulk-destroy" segment is never interpreted as a model ID.
    Route::delete('incomes/bulk-destroy', [IncomeController::class, 'bulkDestroy'])->name('incomes.bulk-destroy');
    Route::delete('budgets/bulk-destroy', [BudgetController::class, 'bulkDestroy'])->name('budgets.bulk-destroy');
    Route::delete('expenses/bulk-destroy', [ExpenseController::class, 'bulkDestroy'])->name('expenses.bulk-destroy');
    Route::delete('debts/bulk-destroy', [DebtController::class, 'bulkDestroy'])->name('debts.bulk-destroy');
    Route::delete('savings-goals/bulk-destroy', [SavingsGoalController::class, 'bulkDestroy'])->name('savings-goals.bulk-destroy');
    Route::delete('savings-contributions/bulk-destroy', [SavingsContributionController::class, 'bulkDestroy'])->name('savings-contributions.bulk-destroy');
    Route::delete('diet-logs/bulk-destroy', [DietLogController::class, 'bulkDestroy'])->name('diet-logs.bulk-destroy');
    Route::delete('exercise-logs/bulk-destroy', [ExerciseLogController::class, 'bulkDestroy'])->name('exercise-logs.bulk-destroy');
    Route::delete('sleep-logs/bulk-destroy', [SleepLogController::class, 'bulkDestroy'])->name('sleep-logs.bulk-destroy');
    Route::delete('health-checkups/bulk-destroy', [HealthCheckupController::class, 'bulkDestroy'])->name('health-checkups.bulk-destroy');
    Route::delete('projects/bulk-destroy', [ProjectController::class, 'bulkDestroy'])->name('projects.bulk-destroy');
    Route::delete('project-tasks/bulk-destroy', [ProjectTaskController::class, 'bulkDestroy'])->name('project-tasks.bulk-destroy');
    Route::delete('meetings/bulk-destroy', [MeetingController::class, 'bulkDestroy'])->name('meetings.bulk-destroy');
    Route::delete('reminders/bulk-destroy', [ReminderController::class, 'bulkDestroy'])->name('reminders.bulk-destroy');
    Route::delete('spiritual-practices/bulk-destroy', [SpiritualPracticeController::class, 'bulkDestroy'])->name('spiritual-practices.bulk-destroy');
    Route::delete('education-plans/bulk-destroy', [EducationPlanController::class, 'bulkDestroy'])->name('education-plans.bulk-destroy');
    Route::delete('network-contacts/bulk-destroy', [NetworkContactController::class, 'bulkDestroy'])->name('network-contacts.bulk-destroy');
    Route::delete('relationships/bulk-destroy', [PersonalRelationshipController::class, 'bulkDestroy'])->name('relationships.bulk-destroy');
    Route::delete('feedback/bulk-destroy', [FeedbackController::class, 'bulkDestroy'])->name('feedback.bulk-destroy');

    Route::get('financial-planner', [FinancialPlannerController::class, 'index'])->name('financial-planner.index');
    Route::put('financial-planner', [FinancialPlannerController::class, 'update'])->name('financial-planner.update');
    Route::get('daily-planner', [DailyPlannerController::class, 'index'])->name('daily-planner.index');
    Route::put('daily-planner', [DailyPlannerController::class, 'updatePlan'])->name('daily-planner.update');
    Route::post('daily-planner/items', [DailyPlannerController::class, 'storeItem'])->name('daily-planner.items.store');
    Route::patch('daily-planner/items/{item}/toggle', [DailyPlannerController::class, 'toggle'])->name('daily-planner.items.toggle');
    Route::put('daily-planner/items/{item}', [DailyPlannerController::class, 'updateItem'])->name('daily-planner.items.update');
    Route::delete('daily-planner/items/bulk-destroy', [DailyPlannerController::class, 'bulkDestroy'])->name('daily-planner.items.bulk-destroy');
    Route::delete('daily-planner/items/{item}', [DailyPlannerController::class, 'destroyItem'])->name('daily-planner.items.destroy');

    // Legacy Plans page was replaced by Daily Planner. Keep only a safe redirect for old bookmarks.
    Route::get('plans', fn () => redirect()->route('annual-plans.index'))->name('plans.index');

    Route::get('annual-plans', [PlanController::class, 'index'])->name('annual-plans.index');
    Route::post('annual-plans', [PlanController::class, 'store'])->name('annual-plans.store');
    Route::put('annual-plans/{annualPlan}', [PlanController::class, 'update'])->name('annual-plans.update');
    Route::patch('annual-plans/{annualPlan}/toggle', [PlanController::class, 'toggle'])->name('annual-plans.toggle');
    Route::delete('annual-plans/bulk-destroy', [PlanController::class, 'bulkDestroy'])->name('annual-plans.bulk-destroy');
    Route::delete('annual-plans/{annualPlan}', [PlanController::class, 'destroy'])->name('annual-plans.destroy');
    Route::resource('incomes', IncomeController::class);
    Route::resource('budgets', BudgetController::class);
    Route::resource('expenses', ExpenseController::class);
    Route::resource('debts', DebtController::class);
    Route::resource('savings-goals', SavingsGoalController::class);
    Route::resource('savings-contributions', SavingsContributionController::class);
    Route::resource('diet-logs', DietLogController::class);
    Route::resource('exercise-logs', ExerciseLogController::class);
    Route::resource('sleep-logs', SleepLogController::class);
    Route::resource('health-checkups', HealthCheckupController::class);
    Route::resource('projects', ProjectController::class);
    Route::resource('project-tasks', ProjectTaskController::class);

    Route::post('meetings/multiple', [MeetingController::class, 'storeMultiple'])->name('meetings.store-multiple');
    Route::get('meetings/{meeting}/notes', [MeetingController::class, 'notes'])->name('meetings.notes');
    Route::put('meetings/{meeting}/notes', [MeetingController::class, 'updateNotes'])->name('meetings.notes.update');
    Route::get('meetings/{meeting}/notes/pdf', [MeetingController::class, 'downloadNotesPdf'])->name('meetings.notes.pdf');
    Route::post('meetings/{meeting}/notes/email', [MeetingController::class, 'emailNotes'])->name('meetings.notes.email');

    Route::post('meetings/{meeting}/recordings', [MeetingRecordingController::class, 'store'])->name('meetings.recordings.store');
    Route::post('meetings/{meeting}/recordings/upload', [MeetingRecordingController::class, 'upload'])->name('meetings.recordings.upload');
    Route::patch('meeting-recordings/{recording}/status', [MeetingRecordingController::class, 'updateStatus'])->name('meeting-recordings.status');
    Route::post('meeting-recordings/{recording}/stop', [MeetingRecordingController::class, 'stop'])->name('meeting-recordings.stop');
    Route::post('meeting-recordings/{recording}/transcribe', [MeetingRecordingController::class, 'transcribe'])->name('meeting-recordings.transcribe');
    Route::put('meeting-recordings/{recording}/transcript', [MeetingRecordingController::class, 'updateTranscript'])->name('meeting-recordings.transcript.update');
    Route::post('meeting-recordings/{recording}/summarize', [MeetingRecordingController::class, 'generateSummary'])->name('meeting-recordings.summarize');
    Route::post('meeting-recordings/{recording}/process', [MeetingRecordingController::class, 'transcribeAndSummarize'])->name('meeting-recordings.process');
    Route::get('meeting-recordings/{recording}/audio', [MeetingRecordingController::class, 'downloadAudio'])->name('meeting-recordings.audio');
    Route::get('meeting-recordings/{recording}/transcript-download', [MeetingRecordingController::class, 'downloadTranscript'])->name('meeting-recordings.transcript.download');
    Route::get('meeting-recordings/{recording}/summary-download', [MeetingRecordingController::class, 'downloadSummary'])->name('meeting-recordings.summary.download');
    Route::post('meeting-recordings/{recording}/email-summary', [MeetingRecordingController::class, 'emailSummary'])->name('meeting-recordings.email-summary');
    Route::delete('meeting-recordings/{recording}', [MeetingRecordingController::class, 'destroy'])->name('meeting-recordings.destroy');
    Route::get('meetings/connect/{platform}', [MeetingConnectionController::class, 'connect'])->name('meetings.connect');
    Route::get('meetings/connect/{platform}/callback', [MeetingConnectionController::class, 'callback'])->name('meetings.connect.callback');
    Route::delete('meetings/connect/{platform}', [MeetingConnectionController::class, 'disconnect'])->name('meetings.disconnect');
    Route::post('meetings/sync', [MeetingConnectionController::class, 'sync'])->name('meetings.sync');
    Route::resource('meetings', MeetingController::class);
    // Must be registered BEFORE the resource() call below — otherwise
    // Route::resource's GET /reminders/{reminder} would greedily match
    // /reminders/due-now, treating "due-now" as a reminder ID.
    Route::get('reminders/due-now', [ReminderController::class, 'dueNow'])->name('reminders.due-now');
    Route::post('reminders/toggle-mute', [ReminderController::class, 'toggleMute'])->name('reminders.toggle-mute');
    Route::get('reminders/items-for-module', [ReminderController::class, 'itemsForModule'])->name('reminders.items-for-module');
    Route::resource('reminders', ReminderController::class);

    Route::resource('education-plans', EducationPlanController::class);
    Route::resource('network-contacts', NetworkContactController::class);
    Route::resource('relationships', PersonalRelationshipController::class);
    Route::resource('spiritual-practices', SpiritualPracticeController::class);
    Route::resource('feedback', FeedbackController::class);
    Route::get('tips', [TipsController::class, 'index'])->name('tips');
    Route::get('signature', [SignatureController::class, 'show'])->name('signature.show');
    Route::post('signature/signatures', [SignatureController::class, 'storeSignature'])->name('signature.signatures.store');
    Route::delete('signature/signatures/{signature}', [SignatureController::class, 'destroySignature'])->name('signature.signatures.destroy');
    Route::post('signature/documents/preview', [SignatureController::class, 'previewDocument'])->name('signature.documents.preview');
    Route::get('signature/documents/preview-file', [SignatureController::class, 'previewPendingFile'])->name('signature.documents.preview-file');
    Route::post('signature/documents/confirm', [SignatureController::class, 'confirmDocument'])->name('signature.documents.confirm');
    Route::post('signature/documents/cancel', [SignatureController::class, 'cancelPreview'])->name('signature.documents.cancel');
    Route::get('signature/documents/{signedDocument}/download', [SignatureController::class, 'downloadDocument'])->name('signature.documents.download');
    Route::post('signature/documents/bulk-destroy', [SignatureController::class, 'bulkDestroyDocuments'])->name('signature.documents.bulk-destroy');
    Route::delete('signature/documents/{signedDocument}', [SignatureController::class, 'destroyDocument'])->name('signature.documents.destroy');

    Route::get('business-card', [BusinessCardController::class, 'edit'])->name('business-card.edit');

    Route::get('organization', [\App\Http\Controllers\OrganizationController::class, 'show'])->name('organization.show');
    Route::post('organization/invite', [\App\Http\Controllers\OrganizationController::class, 'invite'])->name('organization.invite');
    Route::post('organization/members/{member}/activate', [\App\Http\Controllers\OrganizationController::class, 'activate'])->name('organization.members.activate');
    Route::post('organization/members/{member}/deactivate', [\App\Http\Controllers\OrganizationController::class, 'deactivate'])->name('organization.members.deactivate');
    Route::post('organization/members/{member}/replace', [\App\Http\Controllers\OrganizationController::class, 'replace'])->name('organization.members.replace');
    Route::delete('organization/members/{member}', [\App\Http\Controllers\OrganizationController::class, 'removeMember'])->name('organization.members.remove');

    Route::post('personal-email/send-code', [\App\Http\Controllers\PersonalEmailController::class, 'sendCode'])->name('personal-email.send-code');
    Route::post('personal-email/verify', [\App\Http\Controllers\PersonalEmailController::class, 'verify'])->name('personal-email.verify');
    Route::post('business-card', [BusinessCardController::class, 'update'])->name('business-card.update');
    Route::post('business-card/toggle-published', [BusinessCardController::class, 'togglePublished'])->name('business-card.toggle-published');
    Route::get('business-card/pdf', [BusinessCardController::class, 'downloadPdf'])->name('business-card.pdf');

    // Bring-your-own AI API key management + the AI Planner itself.
    Route::resource('api-credentials', ApiCredentialController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::post('api-credentials/{apiCredential}/activate', [ApiCredentialController::class, 'activate'])
        ->name('api-credentials.activate');

    Route::get('ai-plans', [AiPlanController::class, 'index'])->name('ai-plans.index');
    Route::post('ai-plans', [AiPlanController::class, 'store'])->name('ai-plans.store');
    Route::get('ai-plans/{aiPlan}/pdf', [AiPlanController::class, 'downloadPdf'])->name('ai-plans.pdf');
    Route::delete('ai-plans/{aiPlan}', [AiPlanController::class, 'destroy'])->name('ai-plans.destroy');

    // Full cross-module "Personal Report" as a downloadable PDF (DomPDF).
    Route::get('report/pdf', [ReportController::class, 'download'])->name('report.download');
});

// Loads Breeze's login/register/password-reset/email-verification routes
// (routes/auth.php) — including the 'login' named route that the 'auth'
// middleware redirects to. `breeze:install` normally auto-appends this
// line to web.php; since this file REPLACES that generated web.php, the
// line has to be restored here explicitly, or every auth-protected route
// throws "Route [login] not defined."
require __DIR__.'/auth.php';

// Admin area (account/subscription management, statistics) — see
// routes/admin.php for why it's kept separate and gated by ['auth','admin']
// rather than 'subscribed'.
require __DIR__.'/admin.php';
