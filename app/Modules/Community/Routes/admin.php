<?php

declare(strict_types=1);

use App\Modules\Community\Http\Controllers\Admin\AthleteController;
use App\Modules\Community\Http\Controllers\Admin\AthleteDirectoryController;
use App\Modules\Community\Http\Controllers\Admin\CoachController;
use App\Modules\Community\Http\Controllers\Admin\CommunityActivityController;
use App\Modules\Community\Http\Controllers\Admin\CommunityController;
use App\Modules\Community\Http\Controllers\Admin\CommunityDocumentController;
use App\Modules\Community\Http\Controllers\Admin\OfficialController;
use App\Modules\Community\Http\Controllers\Admin\RegionOverseerController;
use App\Modules\Community\Http\Controllers\Admin\RegionOversightController;
use App\Modules\Community\Http\Controllers\Admin\SportsPersonnelRoleController;
use Illuminate\Support\Facades\Route;

Route::get('sports-personnel-roles', [SportsPersonnelRoleController::class, 'index'])->middleware('permission:community-coach.manage')->name('personnel-roles.index');
Route::post('sports-personnel-roles', [SportsPersonnelRoleController::class, 'store'])->middleware('permission:community-coach.manage')->name('personnel-roles.store');
Route::put('sports-personnel-roles/{role}', [SportsPersonnelRoleController::class, 'update'])->middleware('permission:community-coach.manage')->name('personnel-roles.update');

// Communities
Route::get('communities', [CommunityController::class, 'index'])
    ->middleware('permission:community.view')
    ->name('communities.index');
Route::get('communities/create', [CommunityController::class, 'create'])
    ->middleware('permission:community.create')
    ->name('communities.create');
Route::post('communities', [CommunityController::class, 'store'])
    ->middleware('permission:community.create')
    ->name('communities.store');
Route::get('communities/{community}', [CommunityController::class, 'show'])
    ->middleware('permission:community.view')
    ->name('communities.show');
Route::get('communities/{community}/edit', [CommunityController::class, 'edit'])
    ->middleware('permission:community.update')
    ->name('communities.edit');
Route::put('communities/{community}', [CommunityController::class, 'update'])
    ->middleware('permission:community.update')
    ->name('communities.update');
Route::delete('communities/{community}', [CommunityController::class, 'destroy'])
    ->middleware('permission:community.delete')
    ->name('communities.destroy');
Route::post('communities/{community}/verify', [CommunityController::class, 'verify'])
    ->middleware('permission:community.verify')
    ->name('communities.verify');
Route::post('communities/{community}/revoke', [CommunityController::class, 'revoke'])
    ->middleware('permission:community.verify')
    ->name('communities.revoke');

// Sub-resources under communities/{community}
Route::prefix('communities/{community}')->scopeBindings()->group(function (): void {
    // Officials
    Route::get('officials', [OfficialController::class, 'index'])
        ->middleware('permission:community-official.view')
        ->name('communities.officials.index');
    Route::post('officials', [OfficialController::class, 'store'])
        ->middleware('permission:community-official.manage')
        ->name('communities.officials.store');
    Route::delete('officials/{official}', [OfficialController::class, 'destroy'])
        ->middleware('permission:community-official.manage')
        ->name('communities.officials.destroy');

    // Coaches
    Route::get('coaches', [CoachController::class, 'index'])
        ->middleware('permission:community-coach.view')
        ->name('communities.coaches.index');
    Route::post('coaches', [CoachController::class, 'store'])
        ->middleware('permission:community-coach.manage')
        ->name('communities.coaches.store');
    Route::delete('coaches/{coach}', [CoachController::class, 'destroy'])
        ->middleware('permission:community-coach.manage')
        ->name('communities.coaches.destroy');

    // Athletes
    Route::get('athletes', [AthleteController::class, 'index'])
        ->middleware('permission:community-athlete.view')
        ->name('communities.athletes.index');
    Route::post('athletes', [AthleteController::class, 'store'])
        ->middleware('permission:community-athlete.manage')
        ->name('communities.athletes.store');
    Route::post('athletes/{athlete}/release', [AthleteController::class, 'release'])
        ->middleware('permission:community-athlete.manage')
        ->name('communities.athletes.release');

    // Documents
    Route::get('documents', [CommunityDocumentController::class, 'index'])
        ->middleware('permission:community-document.view')
        ->name('communities.documents.index');
    Route::post('documents', [CommunityDocumentController::class, 'store'])
        ->middleware('permission:community-document.manage')
        ->name('communities.documents.store');
    Route::delete('documents/{document}', [CommunityDocumentController::class, 'destroy'])
        ->middleware('permission:community-document.manage')
        ->name('communities.documents.destroy');
    Route::get('documents/{document}/download', [CommunityDocumentController::class, 'download'])
        ->middleware('permission:community-document.view')
        ->name('communities.documents.download');
});
Route::post('documents/{document}/verify', [CommunityDocumentController::class, 'verify'])
    ->middleware('permission:community-document.verify')
    ->name('documents.verify');

// Activities
Route::get('community-activities', [CommunityActivityController::class, 'index'])
    ->middleware('permission:community-activity.view')
    ->name('community-activities.index');
Route::post('community-activities', [CommunityActivityController::class, 'store'])
    ->middleware('permission:community-activity.create')
    ->name('community-activities.store');
Route::put('community-activities/{activity}', [CommunityActivityController::class, 'update'])
    ->middleware('permission:community-activity.update')
    ->name('community-activities.update');
Route::delete('community-activities/{activity}', [CommunityActivityController::class, 'destroy'])
    ->middleware('permission:community-activity.delete')
    ->name('community-activities.destroy');

// Athlete Directory
Route::get('athletes', [AthleteDirectoryController::class, 'index'])
    ->middleware('permission:athlete.view')
    ->name('athletes.index');
Route::get('athletes-export', [AthleteDirectoryController::class, 'export'])
    ->middleware('permission:athlete.export')
    ->name('athletes.export');
Route::get('athletes/{user}', [AthleteDirectoryController::class, 'show'])
    ->middleware('permission:athlete.view')
    ->name('athletes.show');

// Region Oversight
Route::get('region-oversight/athletes', [RegionOversightController::class, 'index'])
    ->middleware('permission:region-oversight.view')
    ->name('region-oversight.athletes.index');
Route::post('region-oversight/athletes/{athlete}/verify', [RegionOversightController::class, 'verify'])
    ->middleware('permission:region-oversight.verify')
    ->name('region-oversight.athletes.verify');
Route::post('region-oversight/athletes/verify-bulk', [RegionOversightController::class, 'verifyBulk'])
    ->middleware('permission:region-oversight.verify-bulk')
    ->name('region-oversight.athletes.verify-bulk');

// Region Overseers Assignment
Route::get('region-overseers', [RegionOverseerController::class, 'index'])
    ->middleware('permission:region-oversight.view')
    ->name('region-overseers.index');
Route::post('region-overseers', [RegionOverseerController::class, 'store'])
    ->middleware('permission:region-oversight.verify')
    ->name('region-overseers.store');
Route::delete('region-overseers/{overseer}', [RegionOverseerController::class, 'destroy'])
    ->middleware('permission:region-oversight.verify')
    ->name('region-overseers.destroy');
