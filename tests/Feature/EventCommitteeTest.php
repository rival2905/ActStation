<?php

declare(strict_types=1);
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCommitteeInvitation;
use App\Modules\Event\Models\EventCommitteeMember;
use App\Modules\Event\Models\EventCommitteeRole;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\EventCommitteeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

test('approved event committee receives only configured scoped menus and loses them immediately on revocation', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('organizer');
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $other = Event::factory()->create();
    $role = EventCommitteeRole::create(['event_id' => $event->id, 'name' => 'Pengamat', 'capabilities' => ['teams.view'], 'is_active' => true]);
    $member = EventCommitteeMember::create(['event_id' => $event->id, 'event_committee_role_id' => $role->id, 'user_id' => $user->id, 'status' => 'pending', 'consented_at' => now()]);
    $this->actingAs($user)->get(route('panel.organizer.teams.index', $event))->assertForbidden();
    $member->update(['status' => 'approved']);
    $this->get(route('panel.organizer.teams.index', $event))->assertOk()->assertDontSee(route('panel.organizer.payouts.index', $event), false);
    $this->get(route('panel.organizer.teams.index', $other))->assertForbidden();
    $this->get(route('panel.organizer.documents.index', $event))->assertForbidden();
    $this->post(route('panel.organizer.committee.roles.store', $event), ['name' => 'Forged'])->assertForbidden();
    $this->get(route('panel.organizer.events.edit', $event))->assertForbidden();
    $role->update(['is_active' => false]);
    $this->get(route('panel.organizer.teams.index', $event))->assertForbidden();
    expect($user->fresh()->getRoleNames()->all())->toBe(['member']);
});

test('shared committee link is stable consented pending scoped and revocable without RBAC grants', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('organizer');
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $event = Event::factory()->create(['user_id' => $owner->id, 'status' => 'published']);
    $other = Event::factory()->create();
    $role = EventCommitteeRole::create(['event_id' => $event->id, 'name' => 'Verifikator', 'capabilities' => ['teams.view', 'teams.review'], 'is_active' => true]);
    $this->actingAs($owner)->post(route('panel.organizer.committee.invitations.store', [$event, $role]))->assertRedirect();
    $invite = EventCommitteeInvitation::where('event_committee_role_id', $role->id)->firstOrFail();
    $token = $invite->token;
    $this->post(route('panel.organizer.committee.invitations.store', [$event, $role]))->assertRedirect();
    expect(EventCommitteeInvitation::where('event_committee_role_id', $role->id)->count())->toBe(1);
    expect($invite->getRawOriginal('token'))->not->toBe($token);
    $this->post(route('panel.organizer.committee.invitations.store', [$other, $role]))->assertForbidden();
    $this->actingAs($user)->get(route('committee-invitations.show', $token))->assertOk()->assertSee('Verifikator');
    expect(EventCommitteeMember::where('event_id', $event->id)->count())->toBe(0);
    $this->post(route('committee-invitations.join', $token), [])->assertSessionHasErrors('consent');
    $this->post(route('committee-invitations.join', $token), ['consent' => 1])->assertRedirect();
    $member = EventCommitteeMember::where('event_id', $event->id)->firstOrFail();
    expect($member->status)->toBe('pending');
    $this->post(route('committee-invitations.join', $token), ['consent' => 1])->assertRedirect();
    expect(EventCommitteeMember::where('event_id', $event->id)->count())->toBe(1);
    $this->put(route('panel.organizer.committee.members.update', [$event, $member]), ['action' => 'approve'])->assertForbidden();
    $this->actingAs($owner)->put(route('panel.organizer.committee.members.update', [$other, $member]), ['action' => 'approve'])->assertForbidden();
    $this->put(route('panel.organizer.committee.members.update', [$event, $member]), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
    expect($member->fresh()->status)->toBe('approved');
    $this->actingAs($user)->get(route('panel.organizer.teams.index', $event))->assertOk();
    $this->get(route('panel.organizer.committee.index', $event))->assertOk()->assertDontSee(route('panel.organizer.committee.roles.store', $event), false);
    $this->actingAs($owner)->delete(route('panel.organizer.committee.invitations.destroy', [$event, $role]))->assertRedirect();
    $this->actingAs($user)->post(route('committee-invitations.join', $token), ['consent' => 1])->assertNotFound();
    $this->get(route('panel.organizer.teams.index', $event))->assertOk();
    $this->actingAs($owner)->put(route('panel.organizer.committee.members.update', [$event, $member]), ['action' => 'end'])->assertRedirect();
    $this->actingAs($user)->get(route('panel.organizer.teams.index', $event))->assertForbidden();
    expect($user->fresh()->getRoleNames()->all())->toBe(['member']);
});

test('capability guards reject forged mutations and permit scheduler and score readers only when granted', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('organizer');
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $participant = EventParticipant::factory()->create(['event_id' => $event->id]);
    $role = EventCommitteeRole::create(['event_id' => $event->id, 'name' => 'Pembaca', 'capabilities' => ['teams.view'], 'is_active' => true]);
    $member = EventCommitteeMember::create(['event_id' => $event->id, 'event_committee_role_id' => $role->id, 'user_id' => $user->id, 'status' => 'approved', 'consented_at' => now()]);
    $this->actingAs($user);
    foreach (['verify', 'requestRevision', 'confirmManualPayment'] as $action) {
        $this->post(route('panel.organizer.teams.'.$action, [$event, $participant]), [])->assertForbidden();
    }
    $this->delete(route('panel.organizer.teams.destroy', [$event, $participant]))->assertForbidden();
    $this->get(route('panel.organizer.tournament.index', $event))->assertForbidden();
    $this->get(route('panel.organizer.results.index', $event))->assertForbidden();
    $role->update(['capabilities' => ['schedule.manage', 'results.manage']]);
    $this->get(route('panel.organizer.tournament.index', $event))->assertOk();
    $this->get(route('panel.organizer.results.index', $event))->assertOk();
    $user->update(['is_active' => false]);
    $this->get(route('panel.organizer.tournament.index', $event))->assertForbidden();
});

test('guest onboarding returns to invitation and malformed access does not mutate data', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('organizer');
    $event = Event::factory()->create(['user_id' => $owner->id, 'status' => 'published']);
    $role = EventCommitteeRole::create(['event_id' => $event->id, 'name' => 'Dokumentasi', 'capabilities' => [], 'is_active' => true]);
    $service = app(EventCommitteeService::class);
    $service->invite($event, $role);
    $invite = EventCommitteeInvitation::where('event_committee_role_id', $role->id)->firstOrFail();
    $token = $invite->token;
    $this->get(route('committee-invitations.login', $token))->assertRedirect(route('panel.login'))->assertSessionHas('url.intended', route('committee-invitations.show', $token));
    $this->get(route('committee-invitations.register', $token))->assertRedirect(route('register'))->assertSessionHas('committee_registration_return', $token);
    $this->get(route('committee-invitations.show', 'invalid'))->assertNotFound();
    expect(EventCommitteeMember::where('event_id', $event->id)->count())->toBe(0);
    $this->actingAs($owner)->put(route('panel.organizer.committee.roles.update', [$event, $role]), ['name' => 'Dokumentasi', 'capabilities' => [['teams.view']]])->assertSessionHasErrors('capabilities.0');
    $this->put(route('panel.organizer.committee.roles.update', [$event, $role]), ['name' => 'Dokumentasi', 'capabilities' => ['payments.confirm']])->assertSessionHasErrors('capabilities');
    expect($role->fresh()->capabilities)->toBe([]);
    $role->update(['is_active' => false]);
    $this->get(route('committee-invitations.show', $token))->assertNotFound();
});

test('inactive applicants cannot be approved and removing role access revokes approved users', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('organizer');
    $user = User::factory()->create(['is_active' => false]);
    $user->assignRole('member');
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $other = Event::factory()->create(['user_id' => $owner->id]);
    $role = EventCommitteeRole::create(['event_id' => $event->id, 'name' => 'Pengamat', 'capabilities' => ['teams.view'], 'is_active' => true]);
    $member = EventCommitteeMember::create(['event_id' => $event->id, 'event_committee_role_id' => $role->id, 'user_id' => $user->id, 'status' => 'pending', 'consented_at' => now()]);
    $this->actingAs($owner)->put(route('panel.organizer.committee.members.update', [$event, $member]), ['action' => 'approve'])->assertSessionHasErrors('action');
    expect($member->fresh()->status)->toBe('pending');
    $this->post(route('panel.organizer.committee.invitations.store', [$other, $role]))->assertNotFound();
    $user->update(['is_active' => true]);
    $this->put(route('panel.organizer.committee.members.update', [$event, $member]), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
    $this->put(route('panel.organizer.committee.members.update', [$event, $member]), ['action' => 'approve'])->assertSessionHasErrors('action');
    $this->actingAs($user)->get(route('panel.organizer.teams.index', $event))->assertOk();
    $this->actingAs($owner)->put(route('panel.organizer.committee.roles.update', [$event, $role]), ['name' => 'Pengamat', 'is_active' => true, 'capabilities' => []])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($user)->get(route('panel.organizer.teams.index', $event))->assertForbidden();
    $this->get(route('panel.organizer.committee.mine'))->assertOk()->assertSee($event->title)->assertDontSee($owner->email);
});

test('owner sees informative access presets and invitation guests see only target role', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('organizer');
    $event = Event::factory()->create(['user_id' => $owner->id, 'status' => 'published']);
    $this->actingAs($owner)->get(route('panel.organizer.committee.index', $event))->assertOk()->assertSee('Tambahkan Jabatan Bawaan');
    expect(EventCommitteeRole::where('event_id', $event->id)->count())->toBe(0);
    $this->post(route('panel.organizer.committee.defaults', $event))->assertRedirect();
    $this->get(route('panel.organizer.committee.index', $event))->assertOk()->assertSee('Bendahara')->assertSee('Menu & Tindakan yang Diizinkan')->assertSee('pencairan');
    $role = EventCommitteeRole::where('event_id', $event->id)->where('name', 'Bendahara')->firstOrFail();
    app(EventCommitteeService::class)->invite($event, $role);
    $invite = EventCommitteeInvitation::where('event_committee_role_id', $role->id)->firstOrFail();
    auth()->logout();
    $this->get(route('committee-invitations.show', $invite->token))->assertOk()->assertSee('Konfirmasi Pembayaran')->assertSee('Buat Akun')->assertDontSee($owner->email);
    $invite->update(['expires_at' => now()->subSecond()]);
    $this->get(route('committee-invitations.show', $invite->token))->assertNotFound();
});

test('committee roles default to no access and presets are editable event scoped templates', function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('organizer');
    $event = Event::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->post(route('panel.organizer.committee.roles.store', $event), ['name' => 'Dokumentasi', 'description' => 'Mendokumentasikan kegiatan'])->assertRedirect()->assertSessionHasNoErrors();
    $role = EventCommitteeRole::where('event_id', $event->id)->firstOrFail();
    expect($role->capabilities)->toBe([]);
    $this->post(route('panel.organizer.committee.defaults', $event))->assertRedirect();
    expect(EventCommitteeRole::where('event_id', $event->id)->count())->toBe(6);
    $this->put(route('panel.organizer.committee.roles.update', [$event, $role]), ['name' => 'Dokumentasi', 'is_active' => true, 'capabilities' => ['teams.view']])->assertRedirect()->assertSessionHasNoErrors();
    expect($role->fresh()->capabilities)->toBe(['teams.view']);
    $this->post(route('panel.organizer.committee.roles.store', $event), ['name' => 'Akses palsu', 'capabilities' => ['superadmin']])->assertSessionHasErrors('capabilities.0');
});
