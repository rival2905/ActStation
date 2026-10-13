<?php

declare(strict_types=1);

use App\Modules\Community\Http\Controllers\Public\InvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('invite/{token}', [InvitationController::class, 'show'])->name('invitations.public.show');
    Route::post('invite/{token}', [InvitationController::class, 'submit'])->name('invitations.public.submit');
});
