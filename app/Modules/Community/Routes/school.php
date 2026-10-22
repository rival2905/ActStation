<?php

declare(strict_types=1);

use App\Modules\Community\Http\Controllers\Panel\SchoolAuthorityController;
use App\Modules\Community\Http\Controllers\Panel\SchoolRegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'EnsurePanelAccess'])->prefix('school-registrations')->name('school-registrations.')->group(function (): void {
    Route::post('{registration}/request-verification', [SchoolRegistrationController::class, 'requestVerification'])->middleware('throttle:5,1')->name('request-verification');
    Route::post('{registration}/official-email', [SchoolAuthorityController::class, 'officialEmail'])->middleware('throttle:3,1')->name('official-email');
    Route::post('{registration}/authority', [SchoolAuthorityController::class, 'store'])->middleware('throttle:3,1')->name('authority');
    Route::get('{registration}', [SchoolRegistrationController::class, 'show'])->name('show');
    Route::post('{registration}/resend', [SchoolRegistrationController::class, 'resend'])->middleware('throttle:3,1')->name('resend');
    Route::get('{registration}/verify', [SchoolRegistrationController::class, 'verify'])->middleware(['signed', 'throttle:10,1'])->name('verify');
    Route::post('{registration}/verify', [SchoolRegistrationController::class, 'consume'])->middleware(['signed', 'throttle:10,1'])->name('consume');
    Route::post('{registration}/evidence', [SchoolRegistrationController::class, 'evidence'])->middleware('throttle:5,1')->name('evidence');
});

Route::middleware(['throttle:10,1'])->prefix('school-authority')->name('school-authority.')->group(function (): void {
    Route::get('{change}', [SchoolAuthorityController::class, 'show'])->middleware('signed')->name('confirm');
    Route::post('{change}', [SchoolAuthorityController::class, 'accept'])->middleware('signed')->name('accept');
    Route::post('{change}/reject', [SchoolAuthorityController::class, 'reject'])->name('reject');
    Route::post('{change}/register', [SchoolAuthorityController::class, 'register'])->name('register');
});
