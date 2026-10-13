<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('admin cannot assign superadmin role when creating user', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Escalated User',
        'email' => 'escalated@actstation.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['superadmin'],
        'is_active' => true,
    ]);

    $response->assertForbidden();
    expect(User::where('email', 'escalated@actstation.test')->exists())->toBeFalse();
});

test('admin cannot assign superadmin role when updating user', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $targetUser = User::factory()->create(['is_active' => true]);
    $targetUser->assignRole('staff');

    $response = $this->actingAs($admin)->put(route('admin.users.update', $targetUser), [
        'name' => $targetUser->name,
        'email' => $targetUser->email,
        'roles' => ['superadmin'],
        'is_active' => true,
    ]);

    $response->assertForbidden();
    $targetUser->refresh();
    expect($targetUser->hasRole('superadmin'))->toBeFalse();
});

test('admin cannot update superadmin account password is_active or roles', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();
    $originalPasswordHash = $superadmin->password;
    $originalName = $superadmin->name;

    $response = $this->actingAs($admin)->put(route('admin.users.update', $superadmin), [
        'name' => 'Hacked Superadmin',
        'email' => $superadmin->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
        'roles' => ['admin'],
        'is_active' => false,
    ]);

    $response->assertForbidden();
    $superadmin->refresh();

    expect($superadmin->name)->toBe($originalName)
        ->and($superadmin->password)->toBe($originalPasswordHash)
        ->and($superadmin->is_active)->toBeTrue()
        ->and($superadmin->hasRole('superadmin'))->toBeTrue()
        ->and($superadmin->hasRole('admin'))->toBeFalse();
});

test('superadmin cannot deactivate own account', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $response = $this->actingAs($superadmin)->put(route('admin.users.update', $superadmin), [
        'name' => $superadmin->name,
        'email' => $superadmin->email,
        'roles' => ['superadmin'],
        'is_active' => false,
    ]);

    $response->assertSessionHas('error');
    $superadmin->refresh();
    expect($superadmin->is_active)->toBeTrue();
});

test('superadmin cannot revoke own superadmin role', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $response = $this->actingAs($superadmin)->put(route('admin.users.update', $superadmin), [
        'name' => $superadmin->name,
        'email' => $superadmin->email,
        'roles' => ['admin'],
        'is_active' => true,
    ]);

    $response->assertSessionHas('error');
    $superadmin->refresh();
    expect($superadmin->hasRole('superadmin'))->toBeTrue();
});

test('superadmin cannot delete another user with superadmin role', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin1 = User::where('email', 'superadmin@actstation.test')->first();

    $superadmin2 = User::factory()->create(['is_active' => true]);
    $superadmin2->assignRole('superadmin');

    $response = $this->actingAs($superadmin1)->delete(route('admin.users.destroy', $superadmin2));

    $response->assertSessionHas('error');
    expect(User::where('id', $superadmin2->id)->exists())->toBeTrue()
        ->and(User::withTrashed()->where('id', $superadmin2->id)->first()->trashed())->toBeFalse();
});

test('login with external role is auto redirected and recorded as login', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $member = User::factory()->create([
        'email' => 'external_member@actstation.test',
        'password' => 'password123',
        'is_active' => true,
    ]);
    $member->assignRole('member');

    $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
        'email' => 'external_member@actstation.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('panel.profile.show'));
    $this->assertAuthenticatedAs($member);

    $loginLog = ActivityLog::where('event', 'login')
        ->where('user_id', $member->id)
        ->first();

    expect($loginLog)->not->toBeNull();
});

test('inactive user login is recorded as login_failed', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $inactive = User::factory()->create([
        'email' => 'inactive_user@actstation.test',
        'password' => 'password123',
        'is_active' => false,
    ]);
    $inactive->assignRole('admin');

    $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
        'email' => 'inactive_user@actstation.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest();

    $failedLog = ActivityLog::where('event', 'login_failed')
        ->where('user_id', $inactive->id)
        ->first();

    expect($failedLog)->not->toBeNull();
});

test('permission attached only to superadmin can be deleted by detaching first', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();
    $permission = Permission::create(['name' => 'temp.permission', 'guard_name' => 'web']);

    $superadminRole = Role::findByName('superadmin', 'web');
    $superadminRole->givePermissionTo($permission);

    // Permission is attached to superadmin role only
    expect($permission->roles()->count())->toBe(1);

    $response = $this->actingAs($superadmin)->delete(route('admin.permissions.destroy', $permission));
    $response->assertRedirect(route('admin.permissions.index'));
    $response->assertSessionHas('success');

    expect(Permission::where('name', 'temp.permission')->exists())->toBeFalse();
});

test('permission attached to role other than superadmin cannot be deleted', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();
    $permission = Permission::create(['name' => 'staff.permission', 'guard_name' => 'web']);

    $staffRole = Role::findByName('staff', 'web');
    $staffRole->givePermissionTo($permission);

    $response = $this->actingAs($superadmin)->delete(route('admin.permissions.destroy', $permission));
    $response->assertSessionHas('error');

    expect(Permission::where('name', 'staff.permission')->exists())->toBeTrue();
});

test('activity log user relation returns soft deleted user withTrashed', function (): void {
    $user = User::factory()->create(['name' => 'Soft Deletable User']);

    $log = ActivityLog::create([
        'user_id' => $user->id,
        'event' => 'created',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
    ]);

    $user->delete();
    expect($user->trashed())->toBeTrue();

    $log->refresh();
    expect($log->user)->not->toBeNull()
        ->and($log->user->id)->toBe($user->id)
        ->and($log->user->name)->toBe('Soft Deletable User');
});

test('avatar upload rejects svg and allows webp png jpg', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('admin');

    // SVG upload should fail
    $svgFile = UploadedFile::fake()->create('avatar.svg', 50, 'image/svg+xml');
    $responseSvg = $this->actingAs($user)->put(route('admin.profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'image' => $svgFile,
    ]);
    $responseSvg->assertSessionHasErrors('image');

    // PNG upload should succeed
    $pngFile = UploadedFile::fake()->image('avatar.png');
    $responsePng = $this->actingAs($user)->put(route('admin.profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'image' => $pngFile,
    ]);
    $responsePng->assertSessionDoesntHaveErrors('image');
});

test('gate before grants superadmin access to any capability', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();
    $staff = User::factory()->create(['is_active' => true]);
    $staff->assignRole('staff');

    expect(Gate::forUser($superadmin)->allows('any.arbitrary.unregistered.permission'))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('any.arbitrary.unregistered.permission'))->toBeFalse();
});
