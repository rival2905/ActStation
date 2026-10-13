<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest is redirected to admin login when accessing protected admin routes', function (): void {
    $response = $this->get('/admin/profile');

    $response->assertRedirect(route('admin.login'));
});

test('user with external role is forbidden (403) from internal admin routes', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('member'); // panel_access: external

    $response = $this->actingAs($user)->get('/admin/profile');

    $response->assertForbidden();
});

test('user with internal or both panel_access role can access internal admin routes', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin'); // panel_access: internal

    $response = $this->actingAs($adminUser)->get('/admin/profile');
    $response->assertOk();

    $superadminUser = User::where('email', 'superadmin@actstation.test')->first();
    $responseSuperadmin = $this->actingAs($superadminUser)->get('/admin/profile');
    $responseSuperadmin->assertOk();
});

test('user without any role is forbidden (403) from internal admin routes', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin/profile');

    $response->assertForbidden();
});
