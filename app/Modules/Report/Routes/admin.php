<?php

declare(strict_types=1);

use App\Modules\Report\Http\Controllers\Admin\CoverageMapController;
use App\Modules\Report\Http\Controllers\Admin\GapAnalysisController;
use App\Modules\Report\Http\Controllers\Admin\ReportController;
use App\Modules\Report\Http\Controllers\Admin\ReportExportController;
use Illuminate\Support\Facades\Route;

// Reports
Route::middleware('permission:report.view')->group(function (): void {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
});

Route::middleware('permission:report.user')->group(function (): void {
    Route::get('reports/users', [ReportController::class, 'users'])->name('reports.users');
});

Route::middleware('permission:report.transaction')->group(function (): void {
    Route::get('reports/transactions', [ReportController::class, 'transactions'])->name('reports.transactions');
});

Route::middleware('permission:report.event')->group(function (): void {
    Route::get('reports/events', [ReportController::class, 'events'])->name('reports.events');
});

Route::middleware('permission:report.export')->group(function (): void {
    Route::match(['get', 'post'], 'reports/export', [ReportExportController::class, 'export'])->name('reports.export');
});

// Coverage Map
Route::middleware('permission:coverage.view')->group(function (): void {
    Route::get('coverage-map', [CoverageMapController::class, 'index'])->name('coverage-map.index');
    Route::get('coverage-map/markers', [CoverageMapController::class, 'markers'])->name('coverage-map.markers');
    Route::get('coverage-map/search', [CoverageMapController::class, 'search'])->name('coverage-map.search');
});

// Gap Analysis
Route::middleware('permission:coverage.gap-analysis')->group(function (): void {
    Route::get('coverage-map/gap-analysis', [GapAnalysisController::class, 'index'])->name('coverage-map.gap-analysis');
    Route::get('coverage-map/gap-analysis/schools', [GapAnalysisController::class, 'unreachedSchools'])->name('coverage-map.gap-schools');
});

Route::middleware('permission:report.export')->group(function (): void {
    Route::get('coverage-map/gap-analysis/export', [GapAnalysisController::class, 'export'])->name('coverage-map.gap-export');
});
