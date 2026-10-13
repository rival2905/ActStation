<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\EnsureInternalRole;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    // Guest
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.attempt');
    });

    // Authenticated + Internal Role
    Route::middleware(['auth', EnsureInternalRole::class])->group(function (): void {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dashboard.view')
            ->name('dashboard');

        // Users
        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:user.view')
            ->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])
            ->middleware('permission:user.create')
            ->name('users.create');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:user.create')
            ->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('permission:user.view')
            ->name('users.show');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:user.update')
            ->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])
            ->middleware('permission:user.update')
            ->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware('role:superadmin')
            ->name('users.destroy');

        // Roles
        Route::get('roles', [RoleController::class, 'index'])
            ->middleware('permission:role.view')
            ->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])
            ->middleware('permission:role.create')
            ->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])
            ->middleware('permission:role.create')
            ->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:role.update')
            ->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:role.update')
            ->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:role.delete')
            ->name('roles.destroy');

        // Permissions
        Route::get('permissions', [PermissionController::class, 'index'])
            ->middleware('permission:permission.view')
            ->name('permissions.index');
        Route::get('permissions/create', [PermissionController::class, 'create'])
            ->middleware('permission:permission.create')
            ->name('permissions.create');
        Route::post('permissions', [PermissionController::class, 'store'])
            ->middleware('permission:permission.create')
            ->name('permissions.store');
        Route::get('permissions/{permission}/edit', [PermissionController::class, 'edit'])
            ->middleware('permission:permission.update')
            ->name('permissions.edit');
        Route::put('permissions/{permission}', [PermissionController::class, 'update'])
            ->middleware('permission:permission.update')
            ->name('permissions.update');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])
            ->middleware('permission:permission.delete')
            ->name('permissions.destroy');

        // Profile
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

        // Activity Logs
        Route::get('activity-logs', [ActivityLogController::class, 'index'])
            ->middleware('permission:activity-log.view')
            ->name('activity-logs.index');
        Route::get('activity-logs/export', [ActivityLogController::class, 'export'])
            ->middleware('permission:activity-log.export')
            ->name('activity-logs.export');
    });
});
