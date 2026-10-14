<?php

declare(strict_types=1);

use App\Modules\MasterData\Http\Controllers\RegionController;
use App\Modules\MasterData\Http\Controllers\SchoolController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'EnsurePanelAccess'])->group(function (): void {
    Route::get('regions/provinces', [RegionController::class, 'provinces'])->name('regions.provinces');
    Route::get('regions/cities', [RegionController::class, 'cities'])->name('regions.cities');
    Route::get('regions/districts', [RegionController::class, 'districts'])->name('regions.districts');
    Route::get('regions/villages', [RegionController::class, 'villages'])->name('regions.villages');
    Route::get('regions/postal-code', [RegionController::class, 'postalCode'])->name('regions.postal-code');
    Route::get('schools/search', [SchoolController::class, 'search'])->name('schools.search');
});
