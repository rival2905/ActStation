<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('staff without user.view permission receives 403 on users.index', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $staff = User::factory()->create(['is_active' => true]);
    $staff->assignRole('staff');

    $response = $this->actingAs($staff)->get(route('admin.users.index'));

    $response->assertForbidden();
});

test('admin with user.view permission can access users.index', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
});

test('superadmin role cannot be modified or renamed and keeps all permissions', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();
    $superadminRole = Role::findByName('superadmin', 'web');

    $totalPermissions = Permission::count();
    expect($superadminRole->permissions()->count())->toBe($totalPermissions);

    $response = $this->actingAs($superadmin)->put(route('admin.roles.update', $superadminRole), [
        'name' => 'hacked_admin',
        'panel_access' => 'external',
        'permissions' => [],
    ]);

    $response->assertRedirect(route('admin.roles.index'));

    $superadminRole->refresh();
    expect($superadminRole->name)->toBe('superadmin')
        ->and($superadminRole->panel_access)->toBe('both')
        ->and($superadminRole->permissions()->count())->toBe($totalPermissions);
});

test('superadmin role cannot be deleted', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();
    $superadminRole = Role::findByName('superadmin', 'web');

    $response = $this->actingAs($superadmin)->delete(route('admin.roles.destroy', $superadminRole));

    $response->assertSessionHas('error');
    expect(Role::where('name', 'superadmin')->exists())->toBeTrue();
});

test('non-superadmin user cannot delete user', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $targetUser = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $targetUser));

    $response->assertForbidden();
    expect(User::where('id', $targetUser->id)->exists())->toBeTrue();
});

test('superadmin cannot delete their own account', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();

    $response = $this->actingAs($superadmin)->delete(route('admin.users.destroy', $superadmin));

    $response->assertSessionHas('error');
    expect(User::where('id', $superadmin->id)->exists())->toBeTrue();
});

test('superadmin can soft delete another user', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $superadmin = User::where('email', 'superadmin@actstation.test')->first();
    $targetUser = User::factory()->create();

    $response = $this->actingAs($superadmin)->delete(route('admin.users.destroy', $targetUser));

    $response->assertRedirect(route('admin.users.index'));
    expect(User::withTrashed()->where('id', $targetUser->id)->first()->trashed())->toBeTrue();
});
