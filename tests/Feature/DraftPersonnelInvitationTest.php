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
use Illuminate\Auth\Events\Registered;
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

test('personnel invitation uses athlete style auth card for guest and authenticated statuses', function (): void {
    $this->community->update(['logo' => 'community-logos/personnel-test.png']);
    $token = str_repeat('u', 64);
    DraftPersonnelInvitation::create(['registration_draft_id' => $this->draft->id, 'event_category_id' => $this->category->id, 'token' => $token, 'token_hash' => hash('sha256', $token)]);
    $url = route('personnel-invitations.show', $token);
    $this->get($url)->assertOk()->assertSee('row min-vh-100 flex-center g-0', false)
        ->assertSee('col-md-5 text-center bg-card-gradient', false)->assertSee('col-md-7 d-flex flex-center', false)
        ->assertSee($this->community->name)->assertSee(asset('storage/community-logos/personnel-test.png'), false)->assertSee('Gabung Roster Tenaga Olahraga')
        ->assertSee('data-personnel-form', false)->assertSee('data-personnel-details hidden disabled', false)
        ->assertDontSee('name="birth_date"', false)->assertDontSee('name="jersey_number"', false);
    $member = User::factory()->create(['is_active' => true]);
    $member->assignRole('member');
    CommunityCoach::factory()->create(['community_id' => $this->community->id, 'user_id' => $member->id, 'sports_personnel_role_id' => $this->role->id, 'status' => 'pending', 'ended_at' => null]);
    $this->actingAs($member)->get($url)->assertOk()->assertSee('bg-card-gradient', false)
        ->assertSee('Keanggotaan menunggu persetujuan')->assertDontSee('data-personnel-form', false);
    $this->draft->update(['payload' => ['personnel' => [['user_id' => $member->id, 'role_id' => $this->role->id]]]]);
    $this->get($url)->assertOk()->assertSee('Sudah masuk roster draft ini')->assertDontSee('name="consent"', false);
});

test('personnel email gate distinguishes accounts without exposing profiles or writing membership', function (): void {
    $token = str_repeat('g', 64);
    DraftPersonnelInvitation::create(['registration_draft_id' => $this->draft->id, 'event_category_id' => $this->category->id, 'token' => $token, 'token_hash' => hash('sha256', $token)]);
    $existing = User::factory()->create(['name' => 'Private Person Name', 'email' => 'personnel-existing@example.test']);
    $counts = [User::count(), CommunityCoach::count(), $this->draft->fresh()->revision];
    $this->get(route('personnel-invitations.show', $token))->assertOk()->assertSee('Periksa Akun')->assertSee('data-personnel-details', false);
    $this->postJson('/personnel-invitations/'.$token.'/check', ['email' => $existing->email])->assertOk()
        ->assertJsonPath('registered', true)->assertJsonPath('login_url', route('personnel-invitations.login', $token))->assertDontSee($existing->name);
    $this->postJson('/personnel-invitations/'.$token.'/check', ['email' => 'personnel-new@example.test'])->assertOk()->assertJsonPath('registered', false);
    $this->postJson('/personnel-invitations/'.$token.'/check', ['email' => ['bad']])->assertUnprocessable();
    expect([User::count(), CommunityCoach::count(), $this->draft->fresh()->revision])->toBe($counts);
    $this->draft->update(['expires_at' => now()->subSecond()]);
    $this->postJson('/personnel-invitations/'.$token.'/check', ['email' => $existing->email])->assertNotFound();
});

test('new personnel registers inline without verifying email or gaining management access', function (): void {
    Illuminate\Support\Facades\Event::fake([Registered::class]);
    $token = str_repeat('n', 64);
    DraftPersonnelInvitation::create(['registration_draft_id' => $this->draft->id, 'event_category_id' => $this->category->id, 'token' => $token, 'token_hash' => hash('sha256', $token)]);
    $data = ['email' => 'inline-personnel@example.test', 'name' => 'Personel Baru', 'phone_number' => '089876543210',
        'password' => 'Synthetic-only-pass123', 'password_confirmation' => 'Synthetic-only-pass123', 'role_id' => $this->role->id, 'consent' => 1];
    $url = '/personnel-invitations/'.$token.'/register';
    $this->post($url, array_diff_key($data, ['consent' => true]))->assertSessionHasErrors('consent');
    $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    $this->post($url, array_replace($data, ['role_id' => 999999]))->assertStatus(422);
    $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    $this->post($url, $data)->assertRedirect(route('personnel-invitations.show', $token))->assertSessionHasNoErrors();
    $user = User::where('email', $data['email'])->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->email_verified_at)->toBeNull()->and($user->phone_normalized)->toBe('+6289876543210')
        ->and($user->getRoleNames()->all())->toBe(['member']);
    $this->assertDatabaseHas('community_coaches', ['community_id' => $this->community->id, 'user_id' => $user->id, 'status' => 'pending']);
    $this->assertDatabaseMissing('community_officials', ['user_id' => $user->id]);
    expect($this->draft->fresh()->payload['personnel'])->toBe([])->and($this->draft->fresh()->revision)->toBe(1);
    $this->get(route('personnel-invitations.show', $token))->assertOk()->assertSee('Keanggotaan menunggu persetujuan')->assertDontSee('data-personnel-form', false);
});

test('inline personnel registration rejects existing accounts duplicate phone and expired invitation without changes', function (): void {
    $token = str_repeat('r', 64);
    DraftPersonnelInvitation::create(['registration_draft_id' => $this->draft->id, 'event_category_id' => $this->category->id, 'token' => $token, 'token_hash' => hash('sha256', $token)]);
    $existing = User::factory()->create(['email' => 'protected-personnel@example.test', 'phone_normalized' => '+6289876543210']);
    $data = ['email' => 'other-personnel@example.test', 'name' => 'Not Existing Name', 'phone_number' => '089876543210',
        'password' => 'Synthetic-only-pass123', 'password_confirmation' => 'Synthetic-only-pass123', 'role_id' => $this->role->id, 'consent' => 1];
    $url = '/personnel-invitations/'.$token.'/register';
    $counts = [User::count(), CommunityCoach::count()];
    $this->post($url, array_replace($data, ['email' => $existing->email]))->assertSessionHasErrors('email');
    $this->post($url, $data)->assertSessionHasErrors('phone_normalized');
    $this->postJson($url, array_replace($data, ['phone_number' => ['bad']]))->assertUnprocessable();
    $this->draft->update(['expires_at' => now()->subSecond()]);
    $this->post($url, $data)->assertNotFound();
    expect([User::count(), CommunityCoach::count()])->toBe($counts)->and($existing->fresh()->name)->toBe($existing->name);
    $this->assertGuest();
});

test('existing personnel login preserves invitation and cannot create another account while authenticated', function (): void {
    $token = str_repeat('l', 64);
    DraftPersonnelInvitation::create(['registration_draft_id' => $this->draft->id, 'event_category_id' => $this->category->id, 'token' => $token, 'token_hash' => hash('sha256', $token)]);
    $this->get(route('personnel-invitations.login', $token))->assertRedirect(route('panel.login'))
        ->assertSessionHas('url.intended', route('personnel-invitations.show', $token));
    $count = User::count();
    $this->actingAs($this->manager)->post('/personnel-invitations/'.$token.'/register', [])->assertForbidden();
    expect(User::count())->toBe($count);
    $this->manager->update(['is_active' => false]);
    $this->postJson('/personnel-invitations/'.$token.'/check', ['email' => 'probe@example.test'])->assertForbidden();
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
