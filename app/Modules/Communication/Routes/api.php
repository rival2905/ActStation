<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\AssistantChatController;
use Illuminate\Support\Facades\Route;

// Session auth until Sanctum is installed for the mobile API.
Route::prefix('api/v1/assistant')->name('assistant.')
    ->middleware(['auth:web', 'can:assistant.use', 'throttle:20,1'])
    ->group(function (): void {
        Route::get('archive', [AssistantChatController::class, 'archive'])->name('archive');
        Route::get('history', [AssistantChatController::class, 'history'])->name('history');
        Route::post('messages', [AssistantChatController::class, 'send'])->name('messages');
        Route::delete('history', [AssistantChatController::class, 'clear'])->name('clear');
    });
