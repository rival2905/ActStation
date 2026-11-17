<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\Admin\EmailMessageController;
use App\Modules\Communication\Http\Controllers\Admin\SocialRelayMonitorController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:setting.email')->group(function (): void {
    Route::get('emails', [EmailMessageController::class, 'index'])->name('emails.index');
    Route::get('emails/create', [EmailMessageController::class, 'create'])->name('emails.create');
    Route::post('emails', [EmailMessageController::class, 'store'])->name('emails.store');
    Route::get('emails/{id}', [EmailMessageController::class, 'show'])->name('emails.show');
    Route::post('emails/{id}/retry', [EmailMessageController::class, 'retry'])->name('emails.retry');
    Route::post('emails/preview', [EmailMessageController::class, 'preview'])->name('emails.preview');
    Route::get('social-relay', [SocialRelayMonitorController::class, 'index'])->name('social-relay.index');
});
