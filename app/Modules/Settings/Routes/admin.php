<?php

declare(strict_types=1);

use App\Modules\Settings\Http\Controllers\AgentOfficeSecurityController;
use App\Modules\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings/agent-office')->name('settings.agent-office')->middleware('permission:setting.agent-office-security')->group(function (): void {
    Route::get('', [AgentOfficeSecurityController::class, 'index']);
    Route::post('reauth', [AgentOfficeSecurityController::class, 'reauthenticate'])->block()->name('.reauth');
    Route::put('', [AgentOfficeSecurityController::class, 'update'])->block()->name('.update');
    Route::post('reenable', [AgentOfficeSecurityController::class, 'reenable'])->block()->name('.reenable');
});

Route::get('settings', [SettingsController::class, 'general'])
    ->middleware('permission:setting.view')
    ->name('settings.general');
Route::put('settings/general', [SettingsController::class, 'updateGeneral'])
    ->middleware('permission:setting.update')
    ->name('settings.general.update');

Route::get('settings/payment', [SettingsController::class, 'payment'])
    ->middleware('permission:setting.payment')
    ->name('settings.payment');
Route::put('settings/payment', [SettingsController::class, 'updatePayment'])
    ->middleware('permission:setting.payment')
    ->name('settings.payment.update');

Route::get('settings/email', [SettingsController::class, 'email'])
    ->middleware('permission:setting.email')
    ->name('settings.email');
Route::put('settings/email', [SettingsController::class, 'updateEmail'])
    ->middleware('permission:setting.email')
    ->name('settings.email.update');
