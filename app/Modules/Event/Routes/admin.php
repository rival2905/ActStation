<?php

declare(strict_types=1);

use App\Modules\Event\Http\Controllers\Admin\AdminEscrowPayoutController;
use App\Modules\Event\Http\Controllers\Admin\CategoryController;
use App\Modules\Event\Http\Controllers\Admin\EventCalendarController;
use App\Modules\Event\Http\Controllers\Admin\EventController;
use App\Modules\Event\Http\Controllers\Admin\EventModerationController;
use App\Modules\Event\Http\Controllers\Admin\OrganizerKycModerationController;
use App\Modules\Event\Http\Controllers\Admin\ParticipantController;
use App\Modules\Event\Http\Controllers\Admin\ScheduleController;
use App\Modules\Event\Http\Controllers\Admin\ScheduleSettingController;
use App\Modules\Event\Http\Controllers\Admin\TournamentSchedulerController;
use App\Modules\Event\Http\Controllers\Panel\EventInvitationController;
use App\Modules\Event\Http\Controllers\Panel\EventPersonnelSettingsController;
use App\Modules\Event\Http\Controllers\Panel\EventVenueController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerDocumentController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerGateScannerController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerPayoutController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerResultController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerScheduleController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerTeamController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerTicketController;
use App\Modules\Event\Http\Controllers\Panel\OrganizerWorkspaceController;
use App\Modules\Event\Http\Controllers\Panel\TeamInspectionController;
use Illuminate\Support\Facades\Route;

// Events Blade UI Routes
Route::get('events', [EventController::class, 'index'])
    ->middleware('permission:event.view')
    ->name('events.index');

Route::get('events/create', [EventController::class, 'create'])
    ->middleware('permission:event.create')
    ->name('events.create');

Route::post('events', [EventController::class, 'store'])
    ->middleware('permission:event.create')
    ->name('events.store');

Route::get('events/{event}', [EventController::class, 'show'])
    ->middleware('permission:event.view')
    ->name('events.show');

Route::get('events/{event}/personnel', [EventPersonnelSettingsController::class, 'edit'])->middleware('permission:event.update')->name('events.personnel.edit');
Route::put('events/{event}/personnel', [EventPersonnelSettingsController::class, 'update'])->middleware('permission:event.update')->name('events.personnel.update');

Route::get('events/{event}/edit', [EventController::class, 'edit'])
    ->middleware('permission:event.update')
    ->name('events.edit');

Route::put('events/{event}', [EventController::class, 'update'])
    ->middleware('permission:event.update')
    ->name('events.update');

Route::delete('events/{event}', [EventController::class, 'destroy'])
    ->middleware('permission:event.delete')
    ->name('events.destroy');

Route::post('events/{event}/status', [EventController::class, 'transitionStatus'])
    ->middleware('permission:event.publish')
    ->name('events.status');

Route::post('events/{event}/registration', [EventController::class, 'toggleRegistration'])
    ->middleware('permission:event.update')
    ->name('events.registration');
Route::post('events/{event}/invitations', [EventInvitationController::class, 'store'])
    ->middleware('permission:event-category.update')
    ->name('events.invitations.store');
// Shared event workspace: same controllers/data as organizer panel; internal permissions remain explicit.
Route::scopeBindings()->prefix('events/{event}')->name('events.workspace.')->group(function (): void {
    Route::get('manage', [OrganizerWorkspaceController::class, 'overview'])->middleware('permission:event.view')->name('manage');
    Route::get('documents', [OrganizerDocumentController::class, 'index'])->middleware('permission:event.view')->name('documents.index');
    Route::post('documents', [OrganizerDocumentController::class, 'store'])->middleware('permission:event.update')->name('documents.store');
    Route::get('documents/{document}/download', [OrganizerDocumentController::class, 'download'])->middleware('permission:event.view')->name('documents.download');
    Route::post('documents/{document}/verify', [OrganizerDocumentController::class, 'verify'])->middleware('permission:event.publish')->name('documents.verify');
    Route::post('documents/{document}/reject', [OrganizerDocumentController::class, 'reject'])->middleware('permission:event.publish')->name('documents.reject');
    Route::delete('documents/{document}', [OrganizerDocumentController::class, 'destroy'])->middleware('permission:event.delete')->name('documents.destroy');
    Route::post('documents/{document}/toggle-public', [OrganizerDocumentController::class, 'togglePublic'])->middleware('permission:event.update')->name('documents.togglePublic');
    Route::get('team-inspections/{kind}/{id}', [TeamInspectionController::class, 'show'])->middleware('permission:event-participant.view')->name('teams.inspection.show');
    Route::get('team-inspections/{kind}/{id}/documents/{owner}/{code}/download', [TeamInspectionController::class, 'download'])->middleware('permission:event-participant.view')->name('teams.inspection.download');
    Route::get('team-inspections/{kind}/{id}/personnel-license/{personnelKey}/download', [TeamInspectionController::class, 'downloadPersonnelLicense'])->middleware('permission:event-participant.view')->name('teams.personnel-license.download');
    Route::post('team-inspections/{kind}/{id}/documents/{owner}/{code}/review', [TeamInspectionController::class, 'review'])->middleware('permission:event-participant.confirm')->name('teams.inspection.review');
    Route::post('team-inspections/{kind}/{id}/documents/{owner}/verify-all', [TeamInspectionController::class, 'verifyAll'])->middleware('permission:event-participant.confirm')->name('teams.inspection.verify-all');
    Route::get('teams', [OrganizerTeamController::class, 'index'])->middleware('permission:event-participant.view')->name('teams.index');
    Route::get('teams/{participant}/documents/{document}/download', [OrganizerTeamController::class, 'downloadDocument'])->middleware('permission:event-participant.view')->name('teams.documents.download');
    Route::post('teams/{participant}/verify', [OrganizerTeamController::class, 'verify'])->middleware('permission:event-participant.confirm')->name('teams.verify');
    Route::post('teams/{participant}/request-revision', [OrganizerTeamController::class, 'requestRevision'])->middleware('permission:event-participant.update')->name('teams.requestRevision');
    Route::post('teams/{participant}/confirm-manual-payment', [OrganizerTeamController::class, 'confirmManualPayment'])->middleware('permission:event-participant.confirm')->name('teams.confirmManualPayment');
    Route::delete('teams/{participant}', [OrganizerTeamController::class, 'destroy'])->middleware('permission:event-participant.delete')->name('teams.destroy');
    Route::put('scheduling/{game}/court', [OrganizerScheduleController::class, 'updateCourt'])->middleware('permission:tournament.schedule')->name('scheduling.court.update');
    Route::get('scheduling', [OrganizerScheduleController::class, 'index'])->middleware('permission:event-schedule.view')->name('scheduling.index');
    Route::post('scheduling/auto-recalculate', [OrganizerScheduleController::class, 'autoRecalculate'])->middleware('permission:tournament.schedule')->name('scheduling.autoRecalculate');
    Route::get('results', [OrganizerResultController::class, 'index'])->middleware('permission:event-result.view')->name('results.index');
    Route::post('results/{game}/score', [OrganizerResultController::class, 'recordScore'])->middleware('permission:tournament.manage')->name('results.recordScore');
    Route::post('venues', [EventVenueController::class, 'store'])->middleware('permission:event.update')->name('venues.store');
    Route::post('venues/courts', [EventVenueController::class, 'store'])->middleware('permission:event.update')->name('venues.courts.store');
    Route::get('venues', [EventVenueController::class, 'edit'])->middleware('permission:event.view')->name('venues.edit');
    Route::put('venues', [EventVenueController::class, 'update'])->middleware('permission:event.update')->name('venues.update');
    Route::put('ticketing/sales', [OrganizerTicketController::class, 'updateSales'])->middleware('permission:ticket-category.update')->name('ticketing.sales.update');
    Route::get('ticketing', [OrganizerTicketController::class, 'index'])->middleware('permission:ticket-category.view')->name('ticketing.index');
    Route::post('ticketing', [OrganizerTicketController::class, 'store'])->middleware('permission:ticket-category.create')->name('ticketing.store');
    Route::put('ticketing/{category}', [OrganizerTicketController::class, 'update'])->middleware('permission:ticket-category.update')->name('ticketing.update');
    Route::delete('ticketing/{category}', [OrganizerTicketController::class, 'destroy'])->middleware('permission:ticket-category.delete')->name('ticketing.destroy');
    Route::get('gate-scanner', [OrganizerGateScannerController::class, 'index'])->middleware('permission:ticket-validation.scan')->name('gate-scanner.index');
    Route::post('gate-scanner/verify', [OrganizerGateScannerController::class, 'verify'])->middleware('permission:ticket-validation.scan')->name('gate-scanner.verify');
    Route::get('payouts', [OrganizerPayoutController::class, 'index'])->middleware('permission:transaction.view')->name('payouts.index');
    Route::post('payouts/request', [OrganizerPayoutController::class, 'requestPayout'])->middleware('permission:refund.process')->name('payouts.request');
});

// Moderasi Event Eksternal & Payout Escrow (PRD_EXTERNAL §6.4)
Route::get('events-moderation', [EventModerationController::class, 'index'])
    ->middleware('permission:event.publish')
    ->name('events.moderation.index');

Route::post('events/{event}/approve', [EventModerationController::class, 'approve'])
    ->middleware('permission:event.publish')
    ->name('events.moderation.approve');

Route::post('events/{event}/reject', [EventModerationController::class, 'reject'])
    ->middleware('permission:event.publish')
    ->name('events.moderation.reject');

Route::get('escrow/payouts', [AdminEscrowPayoutController::class, 'index'])
    ->middleware('permission:transaction.view')
    ->name('escrow-payouts.index');

Route::post('escrow/payouts/{payout}/transfer', [AdminEscrowPayoutController::class, 'processTransfer'])
    ->middleware('permission:refund.process')
    ->name('escrow-payouts.transfer');

// Verifikasi & Moderasi KYC Penyelenggara (PRD_EXTERNAL §3.1)
Route::get('organizers-kyc', [OrganizerKycModerationController::class, 'index'])
    ->middleware('permission:user.view')
    ->name('organizer-kyc.index');

Route::get('organizers-kyc/{user}', [OrganizerKycModerationController::class, 'show'])
    ->middleware('permission:user.view')
    ->name('organizer-kyc.show');

Route::post('organizers-kyc/{user}/approve', [OrganizerKycModerationController::class, 'approve'])
    ->middleware('permission:user.update')
    ->name('organizer-kyc.approve');

Route::post('organizers-kyc/{user}/reject', [OrganizerKycModerationController::class, 'reject'])
    ->middleware('permission:user.update')
    ->name('organizer-kyc.reject');

Route::get('organizers-kyc/{user}/documents/{document}/download', [OrganizerKycModerationController::class, 'downloadDocument'])
    ->middleware('permission:user.view')
    ->name('organizer-kyc.documents.download');

Route::get('organizers-kyc/{user}/entity-documents/{code}/download', [OrganizerKycModerationController::class, 'downloadEntityDocument'])
    ->middleware('permission:user.view')
    ->name('organizer-kyc.entity-documents.download');
Route::scopeBindings()->group(function (): void {
    // Categories
    Route::get('events/{event}/categories', [CategoryController::class, 'index'])
        ->middleware('permission:event-category.view')
        ->name('events.categories.index');

    Route::post('events/{event}/categories', [CategoryController::class, 'store'])
        ->middleware('permission:event-category.create')
        ->name('events.categories.store');

    Route::put('events/{event}/categories/{category}', [CategoryController::class, 'update'])
        ->middleware('permission:event-category.update')
        ->name('events.categories.update');

    Route::post('events/{event}/categories/{category}/toggle-roster-visibility', [CategoryController::class, 'toggleRosterVisibility'])
        ->middleware('permission:event-category.update')
        ->name('events.categories.toggleRosterVisibility');

    Route::delete('events/{event}/categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware('permission:event-category.delete')
        ->name('events.categories.destroy');

    // Participants
    Route::get('events/{event}/participants', [ParticipantController::class, 'index'])
        ->middleware('permission:event-participant.view')
        ->name('events.participants.index');

    Route::post('events/{event}/participants', [ParticipantController::class, 'store'])
        ->middleware('permission:event-participant.create')
        ->name('events.participants.store');

    Route::put('events/{event}/participants/{participant}', [ParticipantController::class, 'update'])
        ->middleware('permission:event-participant.update')
        ->name('events.participants.update');

    Route::post('events/{event}/participants/{participant}/status', [ParticipantController::class, 'updateStatus'])
        ->middleware('permission:event-participant.confirm')
        ->name('events.participants.status');

    Route::delete('events/{event}/participants/{participant}', [ParticipantController::class, 'destroy'])
        ->middleware('permission:event-participant.delete')
        ->name('events.participants.destroy');

    // Roster
    Route::get('events/{event}/participants/{participant}/roster', [ParticipantController::class, 'roster'])
        ->middleware('permission:event-participant.update')
        ->name('events.participants.roster');

    Route::put('events/{event}/participants/{participant}/roster', [ParticipantController::class, 'updateRoster'])
        ->middleware('permission:event-participant.update')
        ->name('events.participants.roster.update');

    // Schedules
    Route::get('events/{event}/schedules', [ScheduleController::class, 'index'])
        ->middleware('permission:event-schedule.view')
        ->name('events.schedules.index');

    Route::post('events/{event}/schedules', [ScheduleController::class, 'store'])
        ->middleware('permission:event-schedule.create')
        ->name('events.schedules.store');

    Route::put('events/{event}/schedules/{schedule}', [ScheduleController::class, 'update'])
        ->middleware('permission:event-schedule.update')
        ->name('events.schedules.update');

    Route::delete('events/{event}/schedules/{schedule}', [ScheduleController::class, 'destroy'])
        ->middleware('permission:event-schedule.delete')
        ->name('events.schedules.destroy');
});
// Calendar
Route::get('event-calendar', [EventCalendarController::class, 'index'])
    ->middleware('permission:event.view')
    ->name('events.calendar');

Route::get('event-calendar/feed', [EventCalendarController::class, 'feed'])
    ->middleware('permission:event.view')
    ->name('events.calendar.feed');

// Tournament Scheduler UI
Route::get('events/{event}/scheduler', [TournamentSchedulerController::class, 'mount'])
    ->middleware('permission:tournament.manage')
    ->name('events.scheduler');

Route::get('events/{event}/tournament', [TournamentSchedulerController::class, 'mount'])
    ->middleware('permission:tournament.manage')
    ->name('events.tournament');

// Tournament Scheduler JSON API
Route::prefix('events/{event}/scheduler/api')
    ->name('events.scheduler.api.')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('config', [TournamentSchedulerController::class, 'config'])
            ->middleware('permission:tournament.manage')
            ->name('config');

        Route::post('phases', [TournamentSchedulerController::class, 'storePhase'])
            ->middleware('permission:tournament.manage')
            ->name('phases.store');

        Route::put('phases/{phase}', [TournamentSchedulerController::class, 'updatePhase'])
            ->middleware('permission:tournament.manage')
            ->name('phases.update');

        Route::delete('phases/{phase}', [TournamentSchedulerController::class, 'destroyPhase'])
            ->middleware('permission:tournament.manage')
            ->name('phases.destroy');

        Route::post('phases/{phase}/generate', [TournamentSchedulerController::class, 'generate'])
            ->middleware('permission:tournament.manage')
            ->name('phases.generate');

        Route::post('assign-teams', [TournamentSchedulerController::class, 'assignTeams'])
            ->middleware('permission:tournament.manage')
            ->name('assign-teams');

        Route::post('finalize-game/{game}', [TournamentSchedulerController::class, 'finalizeGame'])
            ->middleware('permission:tournament.manage')
            ->name('finalize-game');

        Route::delete('reset-all', [TournamentSchedulerController::class, 'resetAll'])
            ->middleware('permission:tournament.reset')
            ->name('reset-all');

        Route::post('phases/{phase}/reset', [TournamentSchedulerController::class, 'resetPhase'])
            ->middleware('permission:tournament.reset')
            ->name('phases.reset');

        Route::get('pools', [TournamentSchedulerController::class, 'pools'])
            ->middleware('permission:tournament.manage')
            ->name('pools');

        Route::get('standings', [TournamentSchedulerController::class, 'standings'])
            ->middleware('permission:tournament.manage')
            ->name('standings');

        Route::get('bracket-nodes', [TournamentSchedulerController::class, 'bracketNodes'])
            ->middleware('permission:tournament.manage')
            ->name('bracket-nodes');

        Route::get('games', [TournamentSchedulerController::class, 'games'])
            ->middleware('permission:tournament.manage')
            ->name('games');

        Route::put('games/{game}', [TournamentSchedulerController::class, 'updateGame'])
            ->middleware('permission:tournament.schedule')
            ->name('games.update');

        Route::post('games/{game}/officials', [TournamentSchedulerController::class, 'plotOfficials'])
            ->middleware('permission:tournament.official')
            ->name('games.officials');

        Route::get('officials', [TournamentSchedulerController::class, 'officials'])
            ->middleware('permission:tournament.official')
            ->name('officials');

        Route::get('schedule-settings', [ScheduleSettingController::class, 'getSettings'])
            ->middleware('permission:event-schedule.manage')
            ->name('schedule-settings');

        Route::post('schedule-settings', [ScheduleSettingController::class, 'saveSettings'])
            ->middleware('permission:event-schedule.manage')
            ->name('schedule-settings.save');

        Route::get('schedule-breaks', [ScheduleSettingController::class, 'getBreaks'])
            ->middleware('permission:event-schedule.manage')
            ->name('schedule-breaks');

        Route::post('schedule-breaks', [ScheduleSettingController::class, 'storeBreak'])
            ->middleware('permission:event-schedule.manage')
            ->name('schedule-breaks.store');

        Route::delete('schedule-breaks/{break}', [ScheduleSettingController::class, 'destroyBreak'])
            ->middleware('permission:event-schedule.manage')
            ->name('schedule-breaks.destroy');

        Route::get('courts', [ScheduleSettingController::class, 'getCourts'])
            ->middleware('permission:event-schedule.manage')
            ->name('courts');

        Route::post('courts/sync', [ScheduleSettingController::class, 'syncCourts'])
            ->middleware('permission:event-schedule.manage')
            ->name('courts.sync');

        Route::post('schedule/recalculate', [TournamentSchedulerController::class, 'recalculateSchedule'])
            ->middleware('permission:tournament.schedule')
            ->name('schedule.recalculate');

        Route::get('export/standings-csv', [TournamentSchedulerController::class, 'exportStandingsCsv'])
            ->middleware('permission:tournament.export')
            ->name('export.standings-csv');
    });
