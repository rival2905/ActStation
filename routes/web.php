<?php

declare(strict_types=1);

use App\Modules\Event\Http\Controllers\Public\MyEventController;
use App\Modules\Event\Http\Controllers\Public\MyTicketController;
use App\Modules\Event\Http\Controllers\Public\PublicAuthController;
use App\Modules\Event\Http\Controllers\Public\PublicEventController;
use App\Modules\Event\Http\Controllers\Public\PublicRegistrationController;
use App\Modules\Event\Http\Controllers\Public\PublicResultController;
use App\Modules\Event\Http\Controllers\Public\PublicScheduleController;
use App\Modules\Event\Http\Controllers\Public\PublicTicketPurchaseController;
use App\Modules\Event\Http\Controllers\Public\PublicTournamentController;
use Illuminate\Support\Facades\Route;

// Landing Page Publik (Falcon Theme)
Route::get('/', [PublicEventController::class, 'landing'])->name('landing');
Route::get('panduan-event', fn () => view('public.panduan'))->name('panduan.event');

// Registrasi Publik & Onboarding (PRD_EXTERNAL §2.1)
Route::middleware('guest')->group(function (): void {
    Route::get('register', [PublicAuthController::class, 'showRegisterForm'])->name('register');
    Route::post('register', [PublicAuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register.submit');
});

// Portal Publik Turnamen & Pertandingan (PRD_EXTERNAL §6.1)
Route::prefix('events')->name('events.')->group(function (): void {
    Route::get('/', [PublicEventController::class, 'index'])->name('index');
    Route::get('{slug}', [PublicEventController::class, 'show'])->name('show');
    Route::get('{slug}/tournament', [PublicTournamentController::class, 'index'])->name('tournament');
    Route::get('{slug}/schedule', [PublicScheduleController::class, 'index'])->name('schedule');
    Route::get('{slug}/results', [PublicResultController::class, 'index'])->name('results');
    Route::get('{slug}/documents/{document}/download', [PublicEventController::class, 'downloadDocument'])->name('documents.download');

    // Penjualan Tiket Masuk Penonton
    Route::get('{slug}/tickets', [PublicTicketPurchaseController::class, 'index'])->name('tickets');
    Route::post('{slug}/tickets/checkout', [PublicTicketPurchaseController::class, 'checkout'])->name('tickets.checkout');

    // Pendaftaran Event
    Route::get('{slug}/register', [PublicRegistrationController::class, 'create'])->name('register');
    Route::post('{slug}/register/individual', [PublicRegistrationController::class, 'storeIndividual'])->name('register.individual');
});

// Dashboard Partisipasi & Tiket Pengguna Terautentikasi (PRD_EXTERNAL §6.1)
Route::middleware('auth')->group(function (): void {
    Route::get('my-events', [MyEventController::class, 'index'])->name('my-events.index');
    Route::get('my-tickets', [MyTicketController::class, 'index'])->name('my-tickets.index');
});
