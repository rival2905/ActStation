<?php

declare(strict_types=1);

use App\Modules\Community\Http\Controllers\CommunityLinkMediaController;
use App\Modules\Community\Http\Controllers\Panel\AthleteController;
use App\Modules\Community\Http\Controllers\Panel\Auth\PanelAuthController;
use App\Modules\Community\Http\Controllers\Panel\CoachController;
use App\Modules\Community\Http\Controllers\Panel\CommunityActivityController;
use App\Modules\Community\Http\Controllers\Panel\CommunityLinkPageController;
use App\Modules\Community\Http\Controllers\Panel\CommunitySwitchController;
use App\Modules\Community\Http\Controllers\Panel\DocumentController;
use App\Modules\Community\Http\Controllers\Panel\InvitationController;
use App\Modules\Community\Http\Controllers\Panel\MyCommunityController;
use App\Modules\Community\Http\Controllers\Panel\MyRequestController;
use App\Modules\Community\Http\Controllers\Panel\OfficialController;
use App\Modules\Community\Http\Controllers\Panel\ProfileController;
use App\Modules\Community\Http\Middleware\EnsureValidCommunityContext;
use Illuminate\Support\Facades\Route;

// Guest auth routes
Route::middleware('guest')->group(function (): void {
    Route::get('login', [PanelAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [PanelAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

// Authenticated session termination
Route::post('logout', [PanelAuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Panel access protected routes
Route::middleware(['auth', 'EnsurePanelAccess', EnsureValidCommunityContext::class])->group(function (): void {
    // Switch active community entity
    Route::post('switch', [CommunitySwitchController::class, 'switch'])->name('switch');
    Route::post('community/switch', [CommunitySwitchController::class, 'switch'])->name('community.switch');

    // Community profile & lifecycle verification
    // Community onboarding
    Route::get('community/create', [ProfileController::class, 'create'])->name('community.create');
    Route::post('community', [ProfileController::class, 'store'])->name('community.store');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::prefix('community-links')->name('community-links.')->middleware('throttle:30,1')->group(function (): void {
        Route::post('{page}/avatar', [CommunityLinkMediaController::class, 'upload'])->whereNumber('page')->name('avatar');
        Route::get('{page}/avatar/{asset}', [CommunityLinkMediaController::class, 'preview'])->whereNumber('page')->whereUuid('asset')->name('avatar-preview');
        Route::get('{page}/model/{asset}', [CommunityLinkMediaController::class, 'modelPreview'])->whereNumber('page')->whereUuid('asset')->name('model-preview');
        Route::get('/', [CommunityLinkPageController::class, 'index'])->name('index');
        Route::post('/', [CommunityLinkPageController::class, 'store'])->name('store');
        Route::get('{page}/edit', [CommunityLinkPageController::class, 'edit'])->whereNumber('page')->name('edit');
        Route::put('{page}', [CommunityLinkPageController::class, 'update'])->whereNumber('page')->name('update');
        Route::get('{page}/preview', [CommunityLinkPageController::class, 'preview'])->whereNumber('page')->name('preview');
        foreach (['publish', 'unpublish', 'primary', 'remove-avatar', 'remove-model'] as $action) {
            Route::post('{page}/'.$action, [CommunityLinkPageController::class, 'action'])->whereNumber('page')->name($action);
        }
    });
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('verification', [ProfileController::class, 'requestVerification'])->name('verification.request');

    // Community routine programs (activities)
    Route::post('profile/activities', [CommunityActivityController::class, 'store'])->name('activities.store');
    Route::put('profile/activities/{activity}', [CommunityActivityController::class, 'update'])->name('activities.update');
    Route::delete('profile/activities/{activity}', [CommunityActivityController::class, 'destroy'])->name('activities.destroy');

    // Community officials
    Route::get('officials', [OfficialController::class, 'index'])->name('officials.index');
    Route::post('officials', [OfficialController::class, 'store'])->name('officials.store');
    Route::delete('officials/{official}', [OfficialController::class, 'destroy'])->name('officials.destroy');

    // Community coaches
    Route::post('coaches/{coach}/decision', [CoachController::class, 'decide'])->name('coaches.decide');
    Route::get('coaches', [CoachController::class, 'index'])->name('coaches.index');
    Route::post('coaches', [CoachController::class, 'store'])->name('coaches.store');
    Route::delete('coaches/{coach}', [CoachController::class, 'destroy'])->name('coaches.destroy');

    // Community legal documents
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Community athlete roster & actions
    Route::get('athletes', [AthleteController::class, 'index'])->name('athletes.index');
    Route::post('athletes', [AthleteController::class, 'store'])->name('athletes.store');
    Route::post('athletes/{athlete}/extracurriculars', [AthleteController::class, 'addExtracurricular'])->whereNumber('athlete')->name('athletes.extracurriculars.store');
    Route::post('athletes/{athlete}/approve', [AthleteController::class, 'approve'])->name('athletes.approve');
    Route::post('athletes/{athlete}/reject', [AthleteController::class, 'reject'])->name('athletes.reject');
    Route::post('athletes/{athlete}/release', [AthleteController::class, 'release'])->name('athletes.release');
    Route::post('athletes/{athlete}/resend', [AthleteController::class, 'resend'])->name('athletes.resend');

    // Community invitations
    Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');

    Route::get('my-communities', [MyCommunityController::class, 'index'])->name('my-communities.index');
    Route::get('my-communities/{community}', [MyCommunityController::class, 'show'])->whereNumber('community')->name('my-communities.show');

    // Athlete self-serve roster requests
    Route::get('my-requests', [MyRequestController::class, 'index'])->name('my-requests.index');
    Route::post('my-requests/{athlete}/approve', [MyRequestController::class, 'approve'])->name('my-requests.approve');
    Route::post('my-requests/{athlete}/reject', [MyRequestController::class, 'reject'])->name('my-requests.reject');
});

require __DIR__.'/extracurricular.php';
require __DIR__.'/school.php';
