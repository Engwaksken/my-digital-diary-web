<?php

use App\Http\Controllers\Admin\AdminFeedbackController;
use App\Http\Controllers\Admin\AdminPaymentGatewayController;
use App\Http\Controllers\Admin\AdminPaymentsController;
use App\Http\Controllers\Admin\AdminAiProviderController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminStatisticsController;
use App\Http\Controllers\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Gated by ['auth', 'admin'] — NOT 'subscribed'. Admins manage the
| platform (accounts, subscriptions, login access, statistics); they
| don't need a trial or subscription themselves to do that (see
| User::hasActiveAccess(), which returns true for admins unconditionally).
|
| ADD this require to routes/web.php (see README) — it's kept in its own
| file, same pattern as routes/auth.php, so the admin surface area is easy
| to audit in one place.
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('users/bulk', [AdminUserController::class, 'bulk'])->name('users.bulk');
    Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::post('users/{user}/unsuspend', [AdminUserController::class, 'unsuspend'])->name('users.unsuspend');
    Route::post('users/{user}/subscription', [AdminUserController::class, 'updateSubscription'])->name('users.subscription');
    Route::post('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('statistics', [AdminStatisticsController::class, 'index'])->name('statistics');

    Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::post('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::post('meeting-platforms/{meetingPlatformConfig}', [\App\Http\Controllers\Admin\AdminMeetingPlatformController::class, 'update'])->name('meeting-platforms.update');
    Route::post('ai-providers', [AdminAiProviderController::class, 'store'])->name('ai-providers.store');
    Route::put('ai-providers/{aiProvider}', [AdminAiProviderController::class, 'update'])->name('ai-providers.update');
    Route::post('ai-providers/{aiProvider}/toggle', [AdminAiProviderController::class, 'toggle'])->name('ai-providers.toggle');
    Route::delete('ai-providers/{aiProvider}', [AdminAiProviderController::class, 'destroy'])->name('ai-providers.destroy');

    Route::resource('payment-gateways', AdminPaymentGatewayController::class)->except(['show']);
    Route::delete('announcements/bulk-destroy', [\App\Http\Controllers\Admin\AdminAnnouncementController::class, 'bulkDestroy'])->name('announcements.bulk-destroy');
    Route::resource('announcements', \App\Http\Controllers\Admin\AdminAnnouncementController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::post('payment-gateways/{paymentGateway}/toggle', [AdminPaymentGatewayController::class, 'toggle'])
        ->name('payment-gateways.toggle');
    Route::post('payment-gateways/{paymentGateway}/set-default', [AdminPaymentGatewayController::class, 'setDefault'])
        ->name('payment-gateways.set-default');
    Route::post('payment-gateways/{paymentGateway}/test-connection', [AdminPaymentGatewayController::class, 'testConnection'])
        ->name('payment-gateways.test-connection');

    Route::get('payments', [AdminPaymentsController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/approve', [AdminPaymentsController::class, 'approve'])->name('payments.approve');
    Route::post('payments/{payment}/reject', [AdminPaymentsController::class, 'reject'])->name('payments.reject');

    Route::get('feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
    Route::get('billing-logs', [\App\Http\Controllers\Admin\AdminBillingLogController::class, 'index'])->name('billing-logs.index');
    Route::delete('billing-logs/bulk-destroy', [\App\Http\Controllers\Admin\AdminBillingLogController::class, 'bulkDestroy'])->name('billing-logs.bulk-destroy');
    Route::get('enterprise-inquiries', [\App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class, 'index'])->name('enterprise-inquiries.index');
    Route::delete('enterprise-inquiries/bulk-destroy', [\App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class, 'bulkDestroy'])->name('enterprise-inquiries.bulk-destroy');
    Route::patch('enterprise-inquiries/{enterpriseInquiry}/status', [\App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class, 'updateStatus'])->name('enterprise-inquiries.status');
    Route::post('enterprise-inquiries/{enterpriseInquiry}/quotation', [\App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class, 'sendQuotation'])->name('enterprise-inquiries.quotation');
    Route::post('enterprise-inquiries/{enterpriseInquiry}/invoices/{invoice}/convert', [\App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class, 'convertToInvoice'])->name('enterprise-inquiries.convert-invoice');
    Route::post('enterprise-inquiries/{enterpriseInquiry}/invoices/{invoice}/resend', [\App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class, 'resendInvoice'])->name('enterprise-inquiries.resend-invoice');
    Route::post('enterprise-inquiries/{enterpriseInquiry}/receipt', [\App\Http\Controllers\Admin\AdminEnterpriseInquiryController::class, 'sendReceipt'])->name('enterprise-inquiries.receipt');
    Route::get('invoices', [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'index'])->name('invoices.index');
    Route::delete('invoices/bulk-destroy', [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'bulkDestroy'])->name('invoices.bulk-destroy');
    Route::delete('receipts/bulk-destroy', [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'bulkDestroyReceipts'])->name('receipts.bulk-destroy');
    Route::delete('invoices/{invoice}', [\App\Http\Controllers\Admin\AdminInvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::post('feedback/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedback.update');
    Route::delete('feedback/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('feedback.destroy');

    Route::resource('subscription-plans', AdminSubscriptionPlanController::class)->except(['show']);
    Route::post('subscription-plans/{subscriptionPlan}/toggle', [AdminSubscriptionPlanController::class, 'toggle'])
        ->name('subscription-plans.toggle');
});
