<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('superadmin can create user with roles and password is automatically hashed', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $response = $this->actingAs($superadmin)->post(route('admin.users.store'), [
        'name' => 'New Staff',
        'email' => 'staff1@actstation.test',
        'phone_number' => '081299998888',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'roles' => ['staff'],
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success');

    $user = User::where('email', 'staff1@actstation.test')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('secret12345', $user->password))->toBeTrue()
        ->and($user->hasRole('staff'))->toBeTrue()
        ->and($user->phone_normalized)->toBe('+6281299998888');
});

test('updating user without password retains old password', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $user = User::factory()->create([
        'password' => 'unchanged123',
        'is_active' => true,
    ]);
    $user->assignRole('admin');

    $response = $this->actingAs($superadmin)->put(route('admin.users.update', $user), [
        'name' => 'Updated User Name',
        'email' => $user->email,
        'phone_number' => '081234567890',
        'password' => '',
        'password_confirmation' => '',
        'roles' => ['admin'],
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $user->refresh();
    expect($user->name)->toBe('Updated User Name')
        ->and(Hash::check('unchanged123', $user->password))->toBeTrue();
});

test('superadmin can create role with panel_access and permissions', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $response = $this->actingAs($superadmin)->post(route('admin.roles.store'), [
        'name' => 'finance_manager',
        'panel_access' => 'internal',
        'permissions' => ['dashboard.view', 'user.view'],
    ]);

    $response->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('finance_manager', 'web');
    expect($role)->not->toBeNull()
        ->and($role->panel_access)->toBe('internal')
        ->and($role->hasPermissionTo('user.view'))->toBeTrue()
        ->and($role->hasPermissionTo('dashboard.view'))->toBeTrue();
});

test('superadmin can create and delete permission', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $response = $this->actingAs($superadmin)->post(route('admin.permissions.store'), [
        'name' => 'finance.view',
    ]);

    $response->assertRedirect(route('admin.permissions.index'));
    $permission = Permission::findByName('finance.view', 'web');
    expect($permission)->not->toBeNull();

    $deleteResponse = $this->actingAs($superadmin)->delete(route('admin.permissions.destroy', $permission));
    $deleteResponse->assertRedirect(route('admin.permissions.index'));
    expect(Permission::where('name', 'finance.view')->exists())->toBeFalse();
});

test('user can update profile details', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@actstation.test',
        'phone_number' => '0811111111',
        'is_active' => true,
    ]);
    $user->assignRole('admin');

    $response = $this->actingAs($user)->put(route('admin.profile.update'), [
        'name' => 'New Name',
        'email' => 'new@actstation.test',
        'phone_number' => '0822222222',
    ]);

    $response->assertRedirect(route('admin.profile.edit'));
    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('new@actstation.test')
        ->and($user->phone_normalized)->toBe('+62822222222');
});
