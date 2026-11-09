<?php

declare(strict_types=1);

use App\Modules\Scoreboard\Http\Controllers\ScoreboardController;
use App\Modules\Scoreboard\Http\Middleware\ScoreboardAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/scoreboard')->name('scoreboard.')->middleware(['auth:sanctum', 'can:scoreboard.use', ScoreboardAccess::class, 'throttle:600,1'])->group(function (): void {
    Route::post('sessions', [ScoreboardController::class, 'store'])->name('sessions.store');
    Route::get('sessions/{session}', [ScoreboardController::class, 'snapshot'])->whereUuid('session')->name('sessions.snapshot');
    Route::post('sessions/{session}/commands', [ScoreboardController::class, 'command'])->whereUuid('session')->name('sessions.commands');
    Route::get('sessions/{session}/commands/{command}', [ScoreboardController::class, 'receipt'])->whereUuid('session')->whereUuid('command')->name('sessions.receipt');
    Route::post('sessions/{session}/finish', [ScoreboardController::class, 'finish'])->whereUuid('session')->name('sessions.finish');
});
