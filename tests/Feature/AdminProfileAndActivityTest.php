<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('profile password update requires valid current_password', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'password' => 'old-secret-123',
        'is_active' => true,
    ]);
    $user->assignRole('admin');

    // Wrong current password
    $response = $this->actingAs($user)->put(route('admin.profile.password'), [
        'current_password' => 'wrong-current-password',
        'password' => 'new-secret-456',
        'password_confirmation' => 'new-secret-456',
    ]);

    $response->assertSessionHasErrors('current_password');
    $user->refresh();
    expect(Hash::check('old-secret-123', $user->password))->toBeTrue();

    // Correct current password
    $responseSuccess = $this->actingAs($user)->put(route('admin.profile.password'), [
        'current_password' => 'old-secret-123',
        'password' => 'new-secret-456',
        'password_confirmation' => 'new-secret-456',
    ]);

    $responseSuccess->assertRedirect(route('admin.profile.edit'));
    $responseSuccess->assertSessionHas('success');
    $user->refresh();
    expect(Hash::check('new-secret-456', $user->password))->toBeTrue();
});

test('activity logs index applies filters correctly', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    ActivityLog::create([
        'user_id' => $userA->id,
        'event' => 'login',
        'ip_address' => '127.0.0.1',
    ]);

    ActivityLog::create([
        'user_id' => $userB->id,
        'event' => 'logout',
        'ip_address' => '127.0.0.2',
    ]);

    $response = $this->actingAs($superadmin)->get(route('admin.activity-logs.index', [
        'user_id' => $userA->id,
        'event' => 'login',
    ]));

    $response->assertOk();
    $response->assertSee('127.0.0.1');
    $response->assertDontSee('127.0.0.2');

    $logs = $response->viewData('logs');
    expect($logs->pluck('user_id')->all())->toContain($userA->id)
        ->and($logs->pluck('user_id')->all())->not->toContain($userB->id);
});
test('activity log export streams csv with expected headers and data', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    ActivityLog::create([
        'user_id' => $superadmin->id,
        'event' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $superadmin->id,
        'role_context' => 'superadmin',
        'ip_address' => '192.168.1.100',
        'user_agent' => 'TestBrowser/1.0',
    ]);

    $response = $this->actingAs($superadmin)->get(route('admin.activity-logs.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    expect($content)->toContain('id,created_at,user_name,user_email,event,auditable_type,auditable_id,role_context,ip_address,user_agent')
        ->and($content)->toContain('Superadmin Dev')
        ->and($content)->toContain('superadmin@actstation.test')
        ->and($content)->toContain('192.168.1.100');
});

test('audit log on update redacts sensitive fields and does not log plain values', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::create([
        'name' => 'Initial Name',
        'email' => 'initial@actstation.test',
        'password' => 'initial-secret-123',
        'identity_number' => '3201000000000001',
    ]);

    $user->update([
        'password' => 'updated-secret-456',
        'identity_number' => '3201000000000002',
    ]);

    $updateLog = ActivityLog::where('auditable_type', $user->getMorphClass())
        ->where('auditable_id', $user->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($updateLog)->not->toBeNull()
        ->and($updateLog->old_values['password'])->toBe(ActivityLog::REDACTED)
        ->and($updateLog->new_values['password'])->toBe(ActivityLog::REDACTED)
        ->and($updateLog->old_values['identity_number'])->toBe(ActivityLog::REDACTED)
        ->and($updateLog->new_values['identity_number'])->toBe(ActivityLog::REDACTED)
        ->and(json_encode($updateLog->old_values))->not->toContain('initial-secret-123')
        ->and(json_encode($updateLog->new_values))->not->toContain('updated-secret-456')
        ->and(json_encode($updateLog->old_values))->not->toContain('3201000000000001')
        ->and(json_encode($updateLog->new_values))->not->toContain('3201000000000002');
});
