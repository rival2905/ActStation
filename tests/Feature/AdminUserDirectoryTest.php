<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    Permission::findOrCreate('user.view', 'web');
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('staff');
    $this->admin->givePermissionTo('user.view');
});

test('user directory searches older accounts across server pages and exposes real pagination', function (): void {
    $target = User::factory()->create(['email' => 'older-organizer@example.test']);
    User::factory()->count(101)->create();
    $response = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
        ->assertSee('name="search"', false)->assertSee('name="role"', false)->assertSee('name="is_active"', false)
        ->assertDontSee('data-list-view', false)->assertDontSee('data-list-filter', false)->assertSee('page=2', false);
    expect($response->viewData('users')->contains('id', $target->id))->toBeFalse();
    $filtered = $this->get(route('admin.users.index', ['search' => $target->email]))->assertOk()->assertSee($target->email);
    expect($filtered->viewData('users')->total())->toBe(1);
    $this->getJson(route('admin.users.index', ['search' => $target->email]))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $target->id);
});

test('user directory applies role and inactive filters globally', function (): void {
    $role = Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $target = User::factory()->create(['is_active' => false]);
    $target->assignRole($role);
    User::factory()->create(['is_active' => true])->assignRole($role);
    $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'organizer', 'is_active' => '0']))->assertOk()->assertSee($target->email);
    expect($response->viewData('users')->pluck('id')->all())->toContain($target->id);
    foreach ($response->viewData('users') as $user) {
        expect($user->is_active)->toBeFalse()->and($user->hasRole('organizer'))->toBeTrue();
    }
});
