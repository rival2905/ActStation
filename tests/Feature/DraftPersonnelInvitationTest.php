<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SportsPersonnelRole;
use App\Modules\Event\Models\DraftPersonnelInvitation;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('community_manager');
    $this->community = Community::factory()->create(['is_active' => true]);
    CommunityOfficial::factory()->create(['community_id' => $this->community->id, 'user_id' => $this->manager->id, 'status' => 'active', 'ended_at' => null]);
    $this->role = SportsPersonnelRole::where('code', 'coach')->firstOrFail();
    $this->event = Event::factory()->create(['status' => 'published', 'registration_open' => true, 'registration_deadline' => now()->addDays(5), 'personnel_requirements' => [['role_id' => $this->role->id, 'min' => 0, 'max' => 2]]]);
    $this->category = EventCategory::factory()->create(['event_id' => $this->event->id, 'registration_end' => now()->addDays(5)]);
    $this->draft = RegistrationDraft::create(['event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'community_id' => $this->community->id, 'user_id' => $this->manager->id, 'revision' => 1, 'expires_at' => now()->addDays(5), 'payload' => ['event_category_id' => $this->category->id, 'personnel' => []], 'files' => []]);
});

test('personnel links enforce lifecycle roles max capacity and active target context', function (): void {
    $this->actingAs($this->manager)->withSession(['current_community_id' => $this->community->id]);
    $url = route('panel.registration-drafts.personnel-invitations.store', [$this->event->slug, $this->draft->id]);
    $this->postJson($url, ['revision' => 99])->assertConflict();
    $this->postJson($url, ['revision' => 1])->assertCreated();
    $invite = DraftPersonnelInvitation::where('registration_draft_id', $this->draft->id)->firstOrFail();
    $join = route('personnel-invitations.join', $invite->token);
    $active = User::factory()->create(['is_active' => true]);
    $active->assignRole('member');
    CommunityCoach::factory()->create(['community_id' => $this->community->id, 'user_id' => $active->id, 'sports_personnel_role_id' => $this->role->id, 'status' => 'active', 'ended_at' => null]);
    $this->actingAs($active)->post($join, ['role_id' => 999999, 'consent' => 1])->assertStatus(422);
    expect($this->draft->fresh()->payload['personnel'])->toBe([]);
    $this->event->update(['personnel_requirements' => [['role_id' => $this->role->id, 'min' => 0, 'max' => 1]]]);
    $this->post($join, ['role_id' => $this->role->id, 'consent' => 1])->assertRedirect();
    $another = User::factory()->create(['is_active' => true]);
    $another->assignRole('member');
    CommunityCoach::factory()->create(['community_id' => $this->community->id, 'user_id' => $another->id, 'sports_personnel_role_id' => $this->role->id, 'status' => 'active', 'ended_at' => null]);
    $this->actingAs($another)->post($join, ['role_id' => $this->role->id, 'consent' => 1])->assertSessionHasErrors('personnel');
    expect($this->draft->fresh()->revision)->toBe(2)->and(count($this->draft->fresh()->payload['personnel']))->toBe(1);
    $this->draft->update(['expires_at' => now()->subSecond()]);
    $this->get(route('personnel-invitations.show', $invite->token))->assertNotFound();
    $this->post($join, ['role_id' => $this->role->id, 'consent' => 1])->assertNotFound();
    $this->draft->update(['expires_at' => now()->addDay()]);
    $this->role->update(['is_active' => false]);
    $this->post($join, ['role_id' => $this->role->id, 'consent' => 1])->assertStatus(422);
    expect($this->draft->fresh()->revision)->toBe(2);
});

test('draft page renders both active pickers and removes manual candidate and generic management buttons', function (): void {
    $this->actingAs($this->manager)->withSession(['current_community_id' => $this->community->id]);
    $this->get(route('panel.community-events.create', ['slug' => $this->event->slug, 'draft_id' => $this->draft->id]))
        ->assertOk()->assertSee('Tambah Atlet dari Anggota Aktif')->assertSee('Tambah Coach / Tenaga Pendukung dari Anggota Aktif')
        ->assertSee('data-registration-personnel-invite-link', false)->assertDontSee('data-registration-candidate-refresh', false)->assertDontSee('Kelola Keanggotaan Umum');
});

test('registration result modal renders server success and escaped validation failure', function (): void {
    $this->actingAs($this->manager)->withSession(['current_community_id' => $this->community->id, 'success' => 'Pendaftaran tim berhasil disimpan.']);
    $url = route('panel.community-events.create', ['slug' => $this->event->slug, 'draft_id' => $this->draft->id]);
    $this->get($url)->assertOk()->assertSee('id="registration-result-modal"', false)->assertSee('data-result-pending="1"', false)->assertSee('Pendaftaran tim berhasil disimpan.');
    $this->withSession(['success' => null, 'error' => '<script>not trusted</script>'])->get($url)->assertOk()->assertSee('Pendaftaran belum terkirim')->assertSee('&lt;script&gt;not trusted&lt;/script&gt;', false)->assertDontSee('<script>not trusted</script>', false);
});

test('new personnel requests membership then explicit join after approval enrolls once', function (): void {
    $this->actingAs($this->manager)->withSession(['current_community_id' => $this->community->id]);
    $url = route('panel.registration-drafts.personnel-invitations.store', [$this->event->slug, $this->draft->id]);
    $first = $this->postJson($url, ['revision' => 1])->assertCreated()->assertJsonStructure(['invite_url']);
    $this->postJson($url, ['revision' => 1])->assertCreated()->assertJsonPath('invite_url', $first->json('invite_url'));
    expect(DraftPersonnelInvitation::where('registration_draft_id', $this->draft->id)->count())->toBe(1);
    $invitation = DraftPersonnelInvitation::where('registration_draft_id', $this->draft->id)->firstOrFail();
    $token = $invitation->token;
    $new = User::factory()->create(['is_active' => true]);
    $new->assignRole('member');
    $join = route('personnel-invitations.join', $token);
    $this->actingAs($new)->get(route('personnel-invitations.show', $token))->assertOk()->assertSee('Pelatih');
    expect(CommunityCoach::where('community_id', $this->community->id)->where('user_id', $new->id)->count())->toBe(0);
    $this->post($join, ['role_id' => $this->role->id])->assertSessionHasErrors('consent');
    $this->post($join, ['role_id' => $this->role->id, 'consent' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $membership = CommunityCoach::where('community_id', $this->community->id)->where('user_id', $new->id)->firstOrFail();
    expect($membership->status)->toBe('pending')->and($this->draft->fresh()->payload['personnel'])->toBe([]);
    $this->post($join, ['role_id' => $this->role->id, 'consent' => 1])->assertRedirect();
    expect(CommunityCoach::where('community_id', $this->community->id)->where('user_id', $new->id)->count())->toBe(1);
    $this->post(route('panel.coaches.decide', $membership), ['action' => 'approve', 'panel_community_id' => $this->community->id])->assertStatus(422);
    expect($membership->fresh()->status)->toBe('pending');
    $this->actingAs($this->manager)->withSession(['current_community_id' => $this->community->id])->post(route('panel.coaches.decide', $membership), ['action' => 'approve', 'panel_community_id' => $this->community->id])->assertRedirect()->assertSessionHasNoErrors();
    expect($membership->fresh()->status)->toBe('active');
    $this->actingAs($new)->post($join, ['role_id' => $this->role->id, 'consent' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->draft->fresh()->payload['personnel'])->toBe([['user_id' => $new->id, 'role_id' => $this->role->id]])->and($this->draft->fresh()->revision)->toBe(2);
    $this->post($join, ['role_id' => $this->role->id, 'consent' => 1])->assertRedirect();
    expect($this->draft->fresh()->revision)->toBe(2)->and($new->fresh()->getRoleNames()->all())->toBe(['member']);
});
