<?php

use App\Http\Controllers\IndependentActivationRequestController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Middleware\EnsureSubscribedOrOrganizationMember;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shared Workspace — Web
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', EnsureSubscribedOrOrganizationMember::class])
    ->prefix('workspace')
    ->name('workspace.')
    ->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'index'])
            ->name('index');

        Route::post('/files', [WorkspaceController::class, 'upload'])
            ->middleware('throttle:10,1')
            ->name('files.store');

        Route::get('/files/{file}/download', [WorkspaceController::class, 'download'])
            ->whereNumber('file')
            ->name('files.download');

        Route::delete('/files/{file}', [WorkspaceController::class, 'destroyFile'])
            ->whereNumber('file')
            ->name('files.destroy');

        Route::get('/members', [WorkspaceMemberController::class, 'index'])
            ->name('members.index');

        Route::post('/members/invite', [WorkspaceMemberController::class, 'invite'])
            ->name('members.invite');

        Route::put('/members/{membership}/role', [WorkspaceMemberController::class, 'role'])
            ->whereNumber('membership')
            ->name('members.role');

        Route::post('/members/{membership}/activate', [WorkspaceMemberController::class, 'activate'])
            ->whereNumber('membership')
            ->name('members.activate');

        Route::post('/members/{membership}/suspend', [WorkspaceMemberController::class, 'suspend'])
            ->whereNumber('membership')
            ->name('members.suspend');

        Route::delete('/members/{membership}', [WorkspaceMemberController::class, 'remove'])
            ->whereNumber('membership')
            ->name('members.remove');

        Route::post('/invitations/{invitation}/resend', [WorkspaceMemberController::class, 'resend'])
            ->whereNumber('invitation')
            ->name('invitations.resend');

        Route::delete('/invitations/{invitation}', [WorkspaceMemberController::class, 'cancel'])
            ->whereNumber('invitation')
            ->name('invitations.cancel');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('workspace')
    ->name('workspace.')
    ->group(function (): void {
        Route::get('/invitations/accept/{token}', [WorkspaceMemberController::class, 'showAccept'])
            ->name('invitations.accept');

        Route::post('/invitations/accept/{token}', [WorkspaceMemberController::class, 'accept'])
            ->name('invitations.accept.store');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('account')
    ->name('account.')
    ->group(function (): void {
        Route::get('/independent-activation', [IndependentActivationRequestController::class, 'create'])
            ->name('independent-activation.create');

        Route::post('/independent-activation', [IndependentActivationRequestController::class, 'store'])
            ->name('independent-activation.store');
    });
