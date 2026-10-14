<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login successful with valid internal user redirects to dashboard', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'admin@actstation.test',
        'password' => 'password123',
        'is_active' => true,
    ]);
    $user->assignRole('admin');

    $response = $this->post(route('admin.login.attempt'), [
        'email' => 'admin@actstation.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('login fails with invalid credentials', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'admin@actstation.test',
        'password' => 'password123',
        'is_active' => true,
    ]);
    $user->assignRole('admin');

    $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
        'email' => 'admin@actstation.test',
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect(route('admin.login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive user is rejected even with correct credentials', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'inactive@actstation.test',
        'password' => 'password123',
        'is_active' => false,
    ]);
    $user->assignRole('admin');

    $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
        'email' => 'inactive@actstation.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('admin.login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('user with external only role is auto redirected to their panel from admin login', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'member@actstation.test',
        'password' => 'password123',
        'is_active' => true,
    ]);
    $user->assignRole('member'); // panel_access: external

    $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
        'email' => 'member@actstation.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('panel.community.create'));
    $this->assertAuthenticatedAs($user);
    $this->get(route('panel.community.create'))->assertOk();
    $this->get(route('admin.dashboard'))->assertForbidden();
});

test('user with organizer role is auto redirected to organizer panel from admin login', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'organizer@actstation.test',
        'password' => 'password123',
        'is_active' => true,
    ]);
    $user->assignRole('organizer');

    $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
        'email' => 'organizer@actstation.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('panel.organizer.events.index'));
    $this->assertAuthenticatedAs($user);
});

test('logout clears authentication and invalidates session', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create([
        'is_active' => true,
    ]);
    $user->assignRole('admin');

    $this->actingAs($user);
    $this->assertAuthenticatedAs($user);

    $response = $this->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest();
});
