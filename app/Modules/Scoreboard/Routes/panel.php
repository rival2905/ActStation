<?php

declare(strict_types=1);

use App\Modules\Scoreboard\Http\Controllers\ScoreboardController;
use App\Modules\Scoreboard\Http\Middleware\ScoreboardAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('scoreboard')->name('scoreboard.')->middleware(['auth', ScoreboardAccess::class])->group(function (): void {
    Route::get('/', [ScoreboardController::class, 'index'])->name('index');
    Route::get('sessions/{session}', [ScoreboardController::class, 'show'])->whereUuid('session')->name('show');
    Route::get('sessions/{session}/display/{viewMode}', [ScoreboardController::class, 'display'])->whereUuid('session')->name('display');
});
