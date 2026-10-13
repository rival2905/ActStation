<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login event records activity log', function (): void {
    $user = User::factory()->create();

    event(new Login('web', $user, false));

    $log = ActivityLog::where('user_id', $user->id)
        ->where('event', 'login')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_type)->toBeNull()
        ->and($log->auditable_id)->toBeNull();
});

test('logout event records activity log', function (): void {
    $user = User::factory()->create();

    event(new Logout('web', $user));

    $log = ActivityLog::where('user_id', $user->id)
        ->where('event', 'logout')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_type)->toBeNull();
});

test('login failed event records activity log without password', function (): void {
    $user = User::factory()->create();

    event(new Failed('web', $user, [
        'email' => $user->email,
        'password' => 'super-secret-cleartext',
    ]));

    $log = ActivityLog::where('event', 'login_failed')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values)->toHaveKey('email')
        ->and($log->new_values)->not->toHaveKey('password');
});
