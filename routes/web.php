<?php

declare(strict_types=1);

use App\Http\Controllers\UserProfileController;
use App\Modules\Event\Http\Controllers\Public\MyEventController;
use App\Modules\Event\Http\Controllers\Public\MyTicketController;
use App\Modules\Event\Http\Controllers\Public\PublicAuthController;
use App\Modules\Event\Http\Controllers\Public\PublicEventController;
use App\Modules\Event\Http\Controllers\Public\PublicRegistrationController;
use App\Modules\Event\Http\Controllers\Public\PublicResultController;
use App\Modules\Event\Http\Controllers\Public\PublicScheduleController;
use App\Modules\Event\Http\Controllers\Public\PublicTicketPurchaseController;
use App\Modules\Event\Http\Controllers\Public\PublicTournamentController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Landing Page Publik (Falcon Theme)
Route::get('/', [PublicEventController::class, 'landing'])->name('landing');
Route::get('panduan-event', fn () => view('public.panduan'))->name('panduan.event');
Route::get('sitemap.xml', [PublicEventController::class, 'sitemap'])->name('sitemap');

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
    Route::get('user/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::put('user/profile', [UserProfileController::class, 'update'])->name('user-profile.update');
    Route::get('user/profile/documents/{document}', [UserProfileController::class, 'download'])->whereNumber('document')->name('user-profile.documents.download');
    Route::get('my-events', [MyEventController::class, 'index'])->name('my-events.index');
    Route::post('my-events/{kind}/{id}/documents/{code}', [MyEventController::class, 'upload'])->whereIn('kind', ['draft', 'participant'])->middleware('throttle:20,1')->name('my-events.documents.store');
    Route::get('my-tickets', [MyTicketController::class, 'index'])->name('my-tickets.index');

    // Verifikasi Email (PRD §Komunikasi email n8n)
    Route::get('email/verify', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($request->user()->getPanelHomeRoute());
        }

        return view()->exists('auth.verify-email')
            ? view('auth.verify-email')
            : response('Verification notice', 200);
    })->name('verification.notice');

    Route::get('email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->intended($request->user()->getPanelHomeRoute())
            ->with('verified', true)
            ->with('success', 'Email berhasil diverifikasi.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($request->user()->getPanelHomeRoute())
                ->with('info', 'Email Anda sudah terverifikasi.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')->name('verification.send');
});
