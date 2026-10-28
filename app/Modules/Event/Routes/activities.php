<?php

declare(strict_types=1);

use App\Modules\Event\Http\Controllers\Panel\EventActivityController;
use Illuminate\Support\Facades\Route;

Route::get('events/{event:slug}/activities', [EventActivityController::class, 'index'])->name('events.activities.index');
Route::middleware(['auth', 'EnsurePanelAccess'])->group(function (): void {
    Route::post('panel/events/{event}/activities/{activity}/registrations', [EventActivityController::class, 'register'])->middleware('throttle:10,1')->name('panel.activities.register');
    Route::post('panel/events/{event}/activities/{activity}/registrations/{registration}/accept', [EventActivityController::class, 'accept'])->name('panel.activities.accept');
    Route::get('panel/organizer/events/{event}/activities', [EventActivityController::class, 'manage'])->name('panel.organizer.activities.index');
    Route::post('panel/organizer/events/{event}/activities', [EventActivityController::class, 'store'])->name('panel.organizer.activities.store');
    Route::put('panel/organizer/events/{event}/activities/{activity}', [EventActivityController::class, 'update'])->name('panel.organizer.activities.update');
    Route::post('panel/organizer/events/{event}/activities/{activity}/registrations/{registration}/decision', [EventActivityController::class, 'decide'])->name('panel.organizer.activities.decide');
});
