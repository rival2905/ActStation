<?php

declare(strict_types=1);

use App\Modules\Scoreboard\Http\Controllers\ScoreboardController;
use App\Modules\Scoreboard\Http\Controllers\ShotAccessController;
use App\Modules\Scoreboard\Http\Middleware\ScoreboardAccess;
use Illuminate\Support\Facades\Route;

Route::get('scoreboard/sessions/{session}/display/{viewMode}', [ScoreboardController::class, 'display'])
    ->whereUuid('session')->whereIn('viewMode', ['main', 'shot-left', 'shot-right', 'demo'])
    ->middleware(['signed', 'throttle:120,1'])->name('scoreboard.display');

Route::get('scoreboard/sessions/{session}/shot-control', [ShotAccessController::class, 'show'])
    ->whereUuid('session')->middleware(['signed', 'throttle:120,1'])->name('scoreboard.shot');

Route::prefix('scoreboard')->name('scoreboard.')->middleware(['auth', ScoreboardAccess::class])->group(function (): void {
    Route::get('/', [ScoreboardController::class, 'index'])->name('index');
    Route::get('sessions/{session}', [ScoreboardController::class, 'show'])->whereUuid('session')->name('show');
});
