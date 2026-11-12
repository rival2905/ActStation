<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\SocialRelayController;
use Illuminate\Support\Facades\Route;

foreach (['publish', 'pull', 'ack'] as $action) {
    Route::post($action, [SocialRelayController::class, $action])->defaults('relay_action', $action)->name('agent-office.social-relay.'.$action);
}
