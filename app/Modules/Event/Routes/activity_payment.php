<?php

declare(strict_types=1);

use App\Modules\Event\Http\Controllers\Panel\ActivityPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'EnsurePanelAccess'])->group(function (): void {
    Route::get('panel/events/{event}/activity-registrations/{registration}/checkout', [ActivityPaymentController::class, 'show'])->name('panel.activities.checkout');
    Route::post('panel/events/{event}/activity-registrations/{registration}/proof', [ActivityPaymentController::class, 'upload'])->middleware('throttle:10,1')->name('panel.activities.proof');
    Route::get('panel/events/{event}/activity-registrations/{registration}/proof', [ActivityPaymentController::class, 'download'])->name('panel.activities.download');
    Route::post('panel/organizer/events/{event}/activity-registrations/{registration}/confirm-payment', [ActivityPaymentController::class, 'confirm'])->name('panel.activities.confirm');
});
