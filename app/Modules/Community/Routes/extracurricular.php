<?php

declare(strict_types=1);

use App\Modules\Community\Http\Controllers\Panel\SchoolExtracurricularPersonnelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'EnsurePanelAccess'])
    ->prefix('schools/{community}/extracurriculars/{extracurricular}/personnel')
    ->name('schools.extracurriculars.personnel.')
    ->group(function (): void {
        Route::get('/', [SchoolExtracurricularPersonnelController::class, 'index'])->name('index');
        Route::post('invite', [SchoolExtracurricularPersonnelController::class, 'invite'])->name('invite');
        Route::get('{appointment}/accept', [SchoolExtracurricularPersonnelController::class, 'showAccept'])->name('accept.show');
        Route::post('{appointment}/accept', [SchoolExtracurricularPersonnelController::class, 'accept'])->name('accept');
        Route::post('{appointment}/revoke', [SchoolExtracurricularPersonnelController::class, 'revoke'])->name('revoke');
    });
