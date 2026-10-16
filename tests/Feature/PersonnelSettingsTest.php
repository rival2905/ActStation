<?php

declare(strict_types=1);

use App\Contracts\TeamPersonnelProvider;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SportsPersonnelRole;
use App\Modules\Event\Models\Event;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $this->owner = User::factory()->create(['is_active' => true]);
    $this->owner->assignRole('organizer');
    $this->event = Event::factory()->create(['user_id' => $this->owner->id]);
    $this->role = SportsPersonnelRole::where('code', 'manager')->firstOrFail();
});

test('event owner configures shared optional and mandatory roles but another owner cannot', function (): void {
    $rules = [['role_id' => $this->role->id, 'min' => 1, 'max' => 2]];
    $this->actingAs($this->owner)->get(route('panel.organizer.events.personnel.edit', $this->event))->assertOk()->assertSee('Minimum wajib');
    $this->put(route('panel.organizer.events.personnel.update', $this->event), ['personnel_requirements' => $rules])->assertSessionHasNoErrors()->assertRedirect();
    expect($this->event->fresh()->personnel_requirements)->toEqual($rules);
    $stranger = User::factory()->create(['is_active' => true]);
    $stranger->assignRole('organizer');
    $this->actingAs($stranger)->put(route('panel.organizer.events.personnel.update', $this->event), ['personnel_requirements' => []])->assertForbidden();
    expect($this->event->fresh()->personnel_requirements)->toEqual($rules);
});

test('invalid bounds duplicates and inactive master roles cannot be configured', function (): void {
    $this->actingAs($this->owner);
    $url = route('panel.organizer.events.personnel.update', $this->event);
    $this->put($url, ['personnel_requirements' => [['role_id' => $this->role->id, 'min' => 2, 'max' => 1]]])->assertSessionHasErrors('personnel_requirements.0.max');
    $this->put($url, ['personnel_requirements' => array_fill(0, 2, ['role_id' => $this->role->id, 'min' => 0, 'max' => 1])])->assertSessionHasErrors('personnel_requirements.0.role_id');
    $this->role->update(['is_active' => false]);
    $this->put($url, ['personnel_requirements' => [['role_id' => $this->role->id, 'min' => 0, 'max' => 1]]])->assertSessionHasErrors('personnel_requirements.0.role_id');
});

test('provider excludes inactive accounts pending and ended memberships and never exposes contacts', function (): void {
    $community = Community::factory()->create();
    $active = User::factory()->create(['is_active' => true]);
    CommunityOfficial::factory()->create(['community_id' => $community->id, 'user_id' => $active->id, 'status' => 'active', 'ended_at' => null]);
    foreach (['pending', 'ended', 'inactive_account'] as $state) {
        $user = User::factory()->create(['is_active' => $state !== 'inactive_account']);
        CommunityCoach::factory()->create(['community_id' => $community->id, 'user_id' => $user->id, 'status' => $state === 'pending' ? 'pending' : 'active', 'ended_at' => $state === 'ended' ? today() : null]);
    }
    expect(app(TeamPersonnelProvider::class)->eligiblePeople($community->id))->toBe([['user_id' => $active->id, 'name' => $active->name, 'is_official' => true]]);
});

test('community personnel supports multiple active roles and keeps ended history', function (): void {
    $community = Community::factory()->create();
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole('community_manager');
    CommunityOfficial::factory()->create(['community_id' => $community->id, 'user_id' => $manager->id, 'status' => 'active', 'ended_at' => null]);
    $person = User::factory()->create(['is_active' => true]);
    $coachRole = SportsPersonnelRole::where('code', 'coach')->firstOrFail();
    $officialRole = SportsPersonnelRole::where('code', 'official')->firstOrFail();
    $this->actingAs($manager)->withSession(['current_community_id' => $community->id]);
    foreach ([$coachRole, $officialRole] as $role) {
        $this->post(route('panel.coaches.store'), ['panel_community_id' => $community->id, 'user_id' => $person->id, 'sports_personnel_role_id' => $role->id, 'gender_scope' => 'all'])->assertSessionHasNoErrors();
    }
    expect($community->coaches()->where('user_id', $person->id)->count())->toBe(2);
    $this->post(route('panel.coaches.store'), ['panel_community_id' => $community->id, 'user_id' => $person->id, 'sports_personnel_role_id' => $coachRole->id, 'gender_scope' => 'all'])->assertSessionHasErrors('user_id');
    $membership = $community->coaches()->where('user_id', $person->id)->firstOrFail();
    $this->delete(route('panel.coaches.destroy', $membership), ['panel_community_id' => $community->id])->assertRedirect();
    expect($membership->fresh()->status)->toBe('inactive')->and($membership->fresh()->ended_at)->not->toBeNull();
    expect($community->coaches()->where('user_id', $person->id)->count())->toBe(2);
});

test('seeded admin sees personnel master inside master data navigation', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    expect($admin->can('community-coach.manage'))->toBeTrue();
    $this->actingAs($admin)->get(route('admin.personnel-roles.index'))->assertOk();
    $html = view('layouts.partials.sidebar')->render();
    expect($html)->toContain('Jabatan Tenaga Pendukung');
    expect(strpos($html, 'Jabatan Tenaga Pendukung'))->toBeGreaterThan(strpos($html, '>Master Data</div>'));
});

test('only admin can create master roles and organizer cannot reach master', function (): void {
    $this->actingAs($this->owner)->get(route('admin.personnel-roles.index'))->assertForbidden();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('superadmin');
    $this->actingAs($admin)->post(route('admin.personnel-roles.store'), ['name' => 'Dokter Tim', 'code' => 'team_doctor_test', 'sort_order' => 10, 'is_active' => 1])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('sports_personnel_roles', ['code' => 'team_doctor_test', 'name' => 'Dokter Tim']);
});
