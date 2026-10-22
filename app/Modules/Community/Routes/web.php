<?php

declare(strict_types=1);

use App\Modules\Community\Http\Controllers\CommunityLinkMediaController;
use App\Modules\Community\Http\Controllers\CommunityLinkPublicController;
use App\Modules\Community\Http\Controllers\Public\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('c/{handle}/avatar/{asset}', [CommunityLinkMediaController::class, 'avatar'])->where('handle', '[a-z0-9-]+')->whereUuid('asset')->middleware('throttle:120,1')->name('community-links.avatar');

Route::get('c/{handle}/model/{asset}', [CommunityLinkMediaController::class, 'model'])->where('handle', '[a-z0-9-]+')->whereUuid('asset')->middleware('throttle:120,1')->name('community-links.model');

Route::get('c/{handle}', [CommunityLinkPublicController::class, 'show'])->where('handle', '[a-z0-9-]+')->middleware('throttle:120,1')->name('community-links.show');
Route::get('c/{handle}/r/{linkId}', [CommunityLinkPublicController::class, 'click'])->where('handle', '[a-z0-9-]+')->whereUuid('linkId')->middleware('throttle:120,1')->name('community-links.click');

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('invite/{token}', [InvitationController::class, 'show'])->name('invitations.public.show');
    Route::get('invite/{token}/login', [InvitationController::class, 'login'])->name('invitations.public.login');
    Route::post('invite/{token}', [InvitationController::class, 'submit'])->name('invitations.public.submit');
    Route::post('invite/{token}/join', [InvitationController::class, 'join'])
        ->middleware('auth:web')
        ->name('invitations.public.join');
});
