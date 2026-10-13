<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('created event records activity log with redacted sensitive fields', function (): void {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret-password',
        'identity_number' => '3201234567890001',
        'phone_number' => '08123456789',
    ]);

    $log = ActivityLog::where('auditable_type', $user->getMorphClass())
        ->where('auditable_id', $user->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values)->toBeArray()
        ->and($log->new_values['name'])->toBe('John Doe')
        ->and($log->new_values['password'])->toBe(ActivityLog::REDACTED)
        ->and($log->new_values['identity_number'])->toBe(ActivityLog::REDACTED);
});

test('update without changes does not write activity log', function (): void {
    $user = User::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret-password',
    ]);

    $initialLogsCount = ActivityLog::count();

    // Save with no changes
    $user->save();
    expect(ActivityLog::count())->toBe($initialLogsCount);

    // Save assigning same value
    $user->name = 'Jane Doe';
    $user->save();
    expect(ActivityLog::count())->toBe($initialLogsCount);
});

test('updated event only records changed fields with sensitive fields redacted', function (): void {
    $user = User::create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'password' => 'secret-password',
        'identity_number' => '1111111111111111',
    ]);

    $user->name = 'Updated Name';
    $user->identity_number = '2222222222222222';
    $user->save();

    $log = ActivityLog::where('auditable_type', $user->getMorphClass())
        ->where('auditable_id', $user->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values)->toHaveKeys(['name', 'identity_number'])
        ->and($log->old_values)->not->toHaveKey('email')
        ->and($log->new_values)->toHaveKeys(['name', 'identity_number'])
        ->and($log->new_values['name'])->toBe('Updated Name')
        ->and($log->old_values['identity_number'])->toBe(ActivityLog::REDACTED)
        ->and($log->new_values['identity_number'])->toBe(ActivityLog::REDACTED);
});
