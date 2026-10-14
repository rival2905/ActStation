<?php

declare(strict_types=1);

use App\Modules\Event\Http\Controllers\Admin\ScheduleSettingController;
use App\Modules\Event\Http\Controllers\Admin\TournamentSchedulerController;
use App\Modules\Event\Http\Controllers\Panel\CommunityCheckoutController;
use App\Modules\Event\Http\Controllers\Panel\CommunityEventRegistrationController;
use App\Modules\Event\Http\Controllers\Panel\CommunityRosterController;
use App\Modules\Event\Http\Controllers\Panel\EventInvitationController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerCategoryController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerDocumentController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerEventController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerGateScannerController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerKycController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerPayoutController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerResultController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerScheduleController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerTeamController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerTicketController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'EnsurePanelAccess'])->group(function (): void {
    // 1. Panel Registrasi Komunitas (PRD_EXTERNAL §6.3)
    Route::get('events', [CommunityEventRegistrationController::class, 'index'])->name('community-events.index');
    Route::get('community-events', [CommunityEventRegistrationController::class, 'index'])->name('events.index');
    Route::get('events/{slug}/register', [CommunityEventRegistrationController::class, 'create'])->name('community-events.create');
    Route::post('events/{slug}/register', [CommunityEventRegistrationController::class, 'store'])->name('community-events.store');
    Route::get('registrations/{participant}/checkout', [CommunityCheckoutController::class, 'show'])->name('community-events.checkout');
    Route::post('registrations/{participant}/pay-manual', [CommunityCheckoutController::class, 'payManual'])->name('community-events.pay-manual');
    Route::get('registrations/{participant}/roster', [CommunityRosterController::class, 'show'])->name('community-events.roster');

    // 2. Panel Organizer (PRD_EXTERNAL §6.2)
    Route::prefix('organizer')->name('organizer.')->group(function (): void {
        // Just-In-Time KYC
        Route::get('apply', [OrganizerKycController::class, 'show'])->name('apply');
        Route::post('apply', [OrganizerKycController::class, 'store'])->name('apply.store');
        Route::get('apply/documents/{document}/download', [OrganizerKycController::class, 'downloadDocument'])->name('apply.documents.download');

        Route::get('events', [OrganizerEventController::class, 'index'])->name('events.index');
        Route::get('events/create', [OrganizerEventController::class, 'create'])->name('events.create');
        Route::post('events', [OrganizerEventController::class, 'store'])->name('events.store');
        Route::get('events/{event}/edit', [OrganizerEventController::class, 'edit'])->name('events.edit');
        Route::put('events/{event}', [OrganizerEventController::class, 'update'])->name('events.update');
        Route::post('events/{event}/submit-review', [OrganizerEventController::class, 'submitReview'])->name('events.submitReview');
        // Same Vue scheduler engine and API as admin; organizer event ownership checked before dispatch.
        Route::get('events/{event}/tournament', [TournamentSchedulerController::class, 'mount'])
            ->middleware('organizer.event')
            ->name('tournament.index');

        Route::middleware('organizer.event')
            ->scopeBindings()
            ->prefix('events/{event}/scheduler/api')
            ->name('scheduler.api.')
            ->group(function (): void {
                Route::get('config', [TournamentSchedulerController::class, 'config'])->name('config');
                Route::post('phases', [TournamentSchedulerController::class, 'storePhase'])->name('phases.store');
                Route::put('phases/{phase}', [TournamentSchedulerController::class, 'updatePhase'])->name('phases.update');
                Route::delete('phases/{phase}', [TournamentSchedulerController::class, 'destroyPhase'])->name('phases.destroy');
                Route::post('phases/{phase}/generate', [TournamentSchedulerController::class, 'generate'])->name('phases.generate');
                Route::post('assign-teams', [TournamentSchedulerController::class, 'assignTeams'])->name('assign-teams');
                Route::post('finalize-game/{game}', [TournamentSchedulerController::class, 'finalizeGame'])->name('finalize-game');
                Route::delete('reset-all', [TournamentSchedulerController::class, 'resetAll'])->name('reset-all');
                Route::post('phases/{phase}/reset', [TournamentSchedulerController::class, 'resetPhase'])->name('phases.reset');
                Route::get('pools', [TournamentSchedulerController::class, 'pools'])->name('pools');
                Route::get('standings', [TournamentSchedulerController::class, 'standings'])->name('standings');
                Route::get('bracket-nodes', [TournamentSchedulerController::class, 'bracketNodes'])->name('bracket-nodes');
                Route::get('games', [TournamentSchedulerController::class, 'games'])->name('games');
                Route::put('games/{game}', [TournamentSchedulerController::class, 'updateGame'])->name('games.update');
                Route::post('games/{game}/officials', [TournamentSchedulerController::class, 'plotOfficials'])->name('games.officials');
                Route::get('officials', [TournamentSchedulerController::class, 'officials'])->name('officials');
                Route::get('schedule-settings', [ScheduleSettingController::class, 'getSettings'])->name('schedule-settings');
                Route::post('schedule-settings', [ScheduleSettingController::class, 'saveSettings'])->name('schedule-settings.save');
                Route::get('schedule-breaks', [ScheduleSettingController::class, 'getBreaks'])->name('schedule-breaks');
                Route::post('schedule-breaks', [ScheduleSettingController::class, 'storeBreak'])->name('schedule-breaks.store');
                Route::delete('schedule-breaks/{break}', [ScheduleSettingController::class, 'destroyBreak'])->name('schedule-breaks.destroy');
                Route::get('courts', [ScheduleSettingController::class, 'getCourts'])->name('courts');
                Route::post('courts/sync', [ScheduleSettingController::class, 'syncCourts'])->name('courts.sync');
                Route::post('schedule/recalculate', [TournamentSchedulerController::class, 'recalculateSchedule'])->name('schedule.recalculate');
                Route::get('export/standings-csv', [TournamentSchedulerController::class, 'exportStandingsCsv'])->name('export.standings-csv');
            });

        // Workspace
        Route::get('events/{event}/manage', [OrganizerWorkspaceController::class, 'overview'])->name('events.manage');

        // Kategori
        Route::get('events/{event}/categories', [OrganizerCategoryController::class, 'index'])->name('categories.index');
        Route::post('events/{event}/categories', [OrganizerCategoryController::class, 'store'])->name('categories.store');
        Route::put('events/{event}/categories/{category}', [OrganizerCategoryController::class, 'update'])->name('categories.update');
        Route::post('events/{event}/categories/{category}/toggle-roster-visibility', [OrganizerCategoryController::class, 'toggleRosterVisibility'])->name('categories.toggleRosterVisibility');
        Route::delete('events/{event}/categories/{category}', [OrganizerCategoryController::class, 'destroy'])->name('categories.destroy');
        Route::post('events/{event}/invitations', [EventInvitationController::class, 'store'])->name('invitations.store');

        // Dokumen Event (Polymorphic)
        Route::get('events/{event}/documents', [OrganizerDocumentController::class, 'index'])->name('documents.index');
        Route::post('events/{event}/documents', [OrganizerDocumentController::class, 'store'])->name('documents.store');
        Route::get('events/{event}/documents/{document}/download', [OrganizerDocumentController::class, 'download'])->name('documents.download');
        Route::post('events/{event}/documents/{document}/toggle-public', [OrganizerDocumentController::class, 'togglePublic'])->name('documents.togglePublic');
        Route::delete('events/{event}/documents/{document}', [OrganizerDocumentController::class, 'destroy'])->name('documents.destroy');

        // Tim & Roster
        Route::get('events/{event}/teams', [OrganizerTeamController::class, 'index'])->name('teams.index');
        Route::get('events/{event}/teams/{participant}/documents/{document}/download', [OrganizerTeamController::class, 'downloadDocument'])->name('teams.documents.download');
        Route::post('events/{event}/teams/{participant}/verify', [OrganizerTeamController::class, 'verify'])->name('teams.verify');
        Route::post('events/{event}/teams/{participant}/request-revision', [OrganizerTeamController::class, 'requestRevision'])->name('teams.requestRevision');
        Route::post('events/{event}/teams/{participant}/confirm-manual-payment', [OrganizerTeamController::class, 'confirmManualPayment'])->name('teams.confirmManualPayment');
        Route::delete('events/{event}/teams/{participant}', [OrganizerTeamController::class, 'destroy'])->name('teams.destroy');

        // Scheduling
        Route::get('events/{event}/scheduling', [OrganizerScheduleController::class, 'index'])->name('scheduling.index');
        Route::post('events/{event}/scheduling/auto-recalculate', [OrganizerScheduleController::class, 'autoRecalculate'])->name('scheduling.autoRecalculate');

        // Results
        Route::get('events/{event}/results', [OrganizerResultController::class, 'index'])->name('results.index');
        Route::post('events/{event}/results/{game}/score', [OrganizerResultController::class, 'recordScore'])->name('results.recordScore');

        // Ticketing
        Route::get('events/{event}/ticketing', [OrganizerTicketController::class, 'index'])->name('ticketing.index');
        Route::post('events/{event}/ticketing', [OrganizerTicketController::class, 'store'])->name('ticketing.store');
        Route::put('events/{event}/ticketing/{category}', [OrganizerTicketController::class, 'update'])->name('ticketing.update');
        Route::delete('events/{event}/ticketing/{category}', [OrganizerTicketController::class, 'destroy'])->name('ticketing.destroy');

        // Gate Scanner
        Route::get('events/{event}/gate-scanner', [OrganizerGateScannerController::class, 'index'])->name('gate-scanner.index');
        Route::post('events/{event}/gate-scanner/verify', [OrganizerGateScannerController::class, 'verify'])->name('gate-scanner.verify');

        // Payouts
        Route::get('events/{event}/payouts', [OrganizerPayoutController::class, 'index'])->name('payouts.index');
        Route::post('events/{event}/payouts/request', [OrganizerPayoutController::class, 'requestPayout'])->name('payouts.request');
    });
});
