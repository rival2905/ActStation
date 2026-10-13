<?php

declare(strict_types=1);

use App\Modules\MasterData\Http\Controllers\DevelopmentLevelController;
use App\Modules\MasterData\Http\Controllers\DocumentTypeController;
use App\Modules\MasterData\Http\Controllers\RegionController;
use App\Modules\MasterData\Http\Controllers\SchoolController;
use App\Modules\MasterData\Http\Controllers\SportCategoryController;
use App\Modules\MasterData\Http\Controllers\VenueController;
use App\Modules\MasterData\Http\Controllers\VenueCourtController;
use Illuminate\Support\Facades\Route;

// Sport Categories
Route::get('sport-categories', [SportCategoryController::class, 'index'])
    ->middleware('permission:sport-category.view')
    ->name('sport-categories.index');
Route::get('sport-categories/create', [SportCategoryController::class, 'create'])
    ->middleware('permission:sport-category.create')
    ->name('sport-categories.create');
Route::post('sport-categories', [SportCategoryController::class, 'store'])
    ->middleware('permission:sport-category.create')
    ->name('sport-categories.store');
Route::get('sport-categories/{sportCategory}/edit', [SportCategoryController::class, 'edit'])
    ->middleware('permission:sport-category.update')
    ->name('sport-categories.edit');
Route::put('sport-categories/{sportCategory}', [SportCategoryController::class, 'update'])
    ->middleware('permission:sport-category.update')
    ->name('sport-categories.update');
Route::delete('sport-categories/{sportCategory}', [SportCategoryController::class, 'destroy'])
    ->middleware('permission:sport-category.delete')
    ->name('sport-categories.destroy');

// Venues
Route::get('venues', [VenueController::class, 'index'])
    ->middleware('permission:venue.view')
    ->name('venues.index');
Route::get('venues/create', [VenueController::class, 'create'])
    ->middleware('permission:venue.create')
    ->name('venues.create');
Route::post('venues', [VenueController::class, 'store'])
    ->middleware('permission:venue.create')
    ->name('venues.store');
Route::get('venues/{venue}', [VenueController::class, 'show'])
    ->middleware('permission:venue.view')
    ->name('venues.show');
Route::get('venues/{venue}/edit', [VenueController::class, 'edit'])
    ->middleware('permission:venue.update')
    ->name('venues.edit');
Route::put('venues/{venue}', [VenueController::class, 'update'])
    ->middleware('permission:venue.update')
    ->name('venues.update');
Route::delete('venues/{venue}', [VenueController::class, 'destroy'])
    ->middleware('permission:venue.delete')
    ->name('venues.destroy');

// Venue Courts
Route::get('venues/{venue}/courts', [VenueCourtController::class, 'index'])
    ->middleware('permission:venue-court.view')
    ->name('venues.courts.index');
Route::post('venues/{venue}/courts', [VenueCourtController::class, 'store'])
    ->middleware('permission:venue-court.create')
    ->name('venues.courts.store');
Route::put('venues/{venue}/courts/{court}', [VenueCourtController::class, 'update'])
    ->middleware('permission:venue-court.update')
    ->name('venues.courts.update')
    ->scopeBindings();
Route::delete('venues/{venue}/courts/{court}', [VenueCourtController::class, 'destroy'])
    ->middleware('permission:venue-court.delete')
    ->name('venues.courts.destroy')
    ->scopeBindings();

// Development Levels
Route::get('development-levels', [DevelopmentLevelController::class, 'index'])
    ->middleware('permission:development-level.view')
    ->name('development-levels.index');
Route::get('development-levels/create', [DevelopmentLevelController::class, 'create'])
    ->middleware('permission:development-level.create')
    ->name('development-levels.create');
Route::post('development-levels', [DevelopmentLevelController::class, 'store'])
    ->middleware('permission:development-level.create')
    ->name('development-levels.store');
Route::get('development-levels/{developmentLevel}/edit', [DevelopmentLevelController::class, 'edit'])
    ->middleware('permission:development-level.update')
    ->name('development-levels.edit');
Route::put('development-levels/{developmentLevel}', [DevelopmentLevelController::class, 'update'])
    ->middleware('permission:development-level.update')
    ->name('development-levels.update');
Route::delete('development-levels/{developmentLevel}', [DevelopmentLevelController::class, 'destroy'])
    ->middleware('permission:development-level.delete')
    ->name('development-levels.destroy');

// Schools
Route::get('schools', [SchoolController::class, 'index'])
    ->middleware('permission:school.view')
    ->name('schools.index');
Route::get('schools/{school}', [SchoolController::class, 'show'])
    ->middleware('permission:school.view')
    ->name('schools.show');

// Regions
Route::get('regions', [RegionController::class, 'index'])
    ->middleware('permission:region.view')
    ->name('regions.index');
Route::get('regions/provinces', [RegionController::class, 'provinces'])
    ->middleware('permission:region.view')
    ->name('regions.provinces');
Route::get('regions/cities', [RegionController::class, 'cities'])
    ->middleware('permission:region.view')
    ->name('regions.cities');
Route::get('regions/districts', [RegionController::class, 'districts'])
    ->middleware('permission:region.view')
    ->name('regions.districts');
Route::get('regions/villages', [RegionController::class, 'villages'])
    ->middleware('permission:region.view')
    ->name('regions.villages');
Route::get('regions/postal-code', [RegionController::class, 'postalCode'])
    ->middleware('permission:region.view')
    ->name('regions.postal-code');

// Document Types (Master Data)
Route::get('document-types', [DocumentTypeController::class, 'index'])
    ->middleware('permission:document-type.view')
    ->name('document-types.index');
Route::get('document-types/create', [DocumentTypeController::class, 'create'])
    ->middleware('permission:document-type.create')
    ->name('document-types.create');
Route::post('document-types', [DocumentTypeController::class, 'store'])
    ->middleware('permission:document-type.create')
    ->name('document-types.store');
Route::get('document-types/{documentType}/edit', [DocumentTypeController::class, 'edit'])
    ->middleware('permission:document-type.update')
    ->name('document-types.edit');
Route::put('document-types/{documentType}', [DocumentTypeController::class, 'update'])
    ->middleware('permission:document-type.update')
    ->name('document-types.update');
Route::delete('document-types/{documentType}', [DocumentTypeController::class, 'destroy'])
    ->middleware('permission:document-type.delete')
    ->name('document-types.destroy');
