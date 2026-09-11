<?php

use App\Http\Controllers\Api\TeamChatController;
use Illuminate\Support\Facades\Route;

/*
 * Add once to routes/api.php:
 * require __DIR__.'/team_chat_api.php';
 */

Route::middleware('auth:sanctum')
    ->prefix('team-chat')
    ->group(function (): void {
        Route::get('/conversations', [TeamChatController::class, 'conversations']);

        Route::post('/conversations', [TeamChatController::class, 'createConversation']);

        Route::get('/conversations/{conversation}', [TeamChatController::class, 'show']);

        Route::post('/conversations/{conversation}/messages', [TeamChatController::class, 'send']);

        Route::put('/messages/{message}', [TeamChatController::class, 'updateMessage']);

        Route::delete('/messages/{message}', [TeamChatController::class, 'deleteMessage']);

        Route::post('/messages/{message}/reaction', [TeamChatController::class, 'react']);

        Route::get('/attachments/{attachment}', [TeamChatController::class, 'downloadAttachment']);
    });
