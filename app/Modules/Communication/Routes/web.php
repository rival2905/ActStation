<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\Public\AttachmentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('communication/{message}/attachments/{index}', [AttachmentDownloadController::class, 'download'])
    ->name('email.attachments.download')
    ->middleware(['signed', 'throttle:60,1']);
