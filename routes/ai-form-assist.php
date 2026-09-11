<?php

declare(strict_types=1);

use App\Http\Controllers\AiFormAssistController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::post(
        '/ai/form-assist',
        [AiFormAssistController::class, 'generate']
    )->name('ai.form-assist');
});
