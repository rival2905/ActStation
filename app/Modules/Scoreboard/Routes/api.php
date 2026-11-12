<?php

declare(strict_types=1);

use App\Modules\Scoreboard\Http\Controllers\PublicScoreboardController;
use App\Modules\Scoreboard\Http\Controllers\ScoreboardController;
use App\Modules\Scoreboard\Http\Controllers\ShotAccessController;
use App\Modules\Scoreboard\Http\Middleware\ScoreboardAccess;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('api/v1/scoreboard/sessions/{session}/display-snapshot', [ScoreboardController::class, 'displaySnapshot'])
    ->whereUuid('session')->middleware(['signed', 'throttle:600,1'])->name('scoreboard.sessions.display-snapshot');

Route::prefix('api/v1/scoreboard/sessions/{session}/shot-control')->whereUuid('session')
    ->name('scoreboard.shot.')->middleware(['signed', 'throttle:600,1'])->group(function (): void {
        Route::get('snapshot', [ShotAccessController::class, 'snapshot'])->name('snapshot');
        Route::post('commands', [ShotAccessController::class, 'command'])->withoutMiddleware(PreventRequestForgery::class)->name('command');
        Route::get('receipt', [ShotAccessController::class, 'receipt'])->name('receipt');
    });

Route::post('api/v1/scoreboard/personal/sessions', [PublicScoreboardController::class, 'store'])
    ->middleware('throttle:10,1,scoreboard-personal-create:')->block(10, 10)->name('scoreboard.personal.store');
Route::prefix('api/v1/scoreboard/personal')->name('scoreboard.personal.')->middleware('throttle:600,1,scoreboard-personal:')->group(function (): void {
    $controller = PublicScoreboardController::class;
    Route::get('sessions/{session}', [$controller, 'snapshot'])->whereUuid('session')->name('snapshot');
    Route::post('sessions/{session}/commands', [$controller, 'command'])->whereUuid('session')->name('command');
    Route::get('sessions/{session}/commands/{command}', [$controller, 'receipt'])->whereUuid('session')->whereUuid('command')->name('receipt');
    Route::post('sessions/{session}/claim', [$controller, 'claim'])->whereUuid('session')->middleware('auth:web')->block(10, 10)->name('claim');
    Route::post('sessions/{session}/finish', [$controller, 'finish'])->whereUuid('session')->middleware('auth:web')->name('finish');
});

Route::prefix('api/v1/scoreboard')->name('scoreboard.')->middleware(['auth:sanctum', 'can:scoreboard.use', ScoreboardAccess::class, 'throttle:600,1'])->group(function (): void {
    Route::post('sessions/{session}/shot-access', [ShotAccessController::class, 'store'])->whereUuid('session')->name('sessions.shot-access.store');
    Route::delete('sessions/{session}/shot-access', [ShotAccessController::class, 'destroy'])->whereUuid('session')->name('sessions.shot-access.destroy');
    Route::post('sessions', [ScoreboardController::class, 'store'])->name('sessions.store');
    Route::get('sessions/{session}', [ScoreboardController::class, 'snapshot'])->whereUuid('session')->name('sessions.snapshot');
    Route::post('sessions/{session}/commands', [ScoreboardController::class, 'command'])->whereUuid('session')->name('sessions.commands');
    Route::get('sessions/{session}/commands/{command}', [ScoreboardController::class, 'receipt'])->whereUuid('session')->whereUuid('command')->name('sessions.receipt');
    Route::post('sessions/{session}/finish', [ScoreboardController::class, 'finish'])->whereUuid('session')->name('sessions.finish');
});
