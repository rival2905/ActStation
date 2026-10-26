<?php

declare(strict_types=1);

use App\Modules\Report\Http\Controllers\AgentOfficeHealthController;
use Illuminate\Support\Facades\Route;

Route::get('system-health', [AgentOfficeHealthController::class, '__invoke'])->name('agent-office.system-health');
