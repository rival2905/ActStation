<?php

declare(strict_types=1);

use App\Modules\Community\Http\Controllers\Public\InvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('invite/{token}', [InvitationController::class, 'show'])->name('invitations.public.show');
    Route::get('invite/{token}/login', [InvitationController::class, 'login'])->name('invitations.public.login');
    Route::post('invite/{token}', [InvitationController::class, 'submit'])->name('invitations.public.submit');
    Route::post('invite/{token}/join', [InvitationController::class, 'join'])
        ->middleware('auth:web')
        ->name('invitations.public.join');
});
