<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Community\Services\AthleteMembershipService;
use App\Modules\Community\Services\InvitationService;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');

    // Sport categories
    $this->sportCategory1 = DB::table('sport_categories')->insertGetId([
        'name' => 'Basketball',
        'slug' => 'basketball-'.Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->sportCategory2 = DB::table('sport_categories')->insertGetId([
        'name' => 'Volleyball',
        'slug' => 'volleyball-'.Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Communities
    $this->communityA = Community::factory()->create([
        'name' => 'Komunitas Elang',
        'sport_category_id' => $this->sportCategory1,
    ]);

    $this->communityB = Community::factory()->create([
        'name' => 'Komunitas Garuda',
        'sport_category_id' => $this->sportCategory1,
    ]);

    // Manager A
    $this->managerA = User::factory()->create([
        'email' => 'manager_a@actstation.test',
        'is_active' => true,
    ]);
    $this->managerA->assignRole('community_manager');

    CommunityOfficial::create([
        'community_id' => $this->communityA->id,
        'user_id' => $this->managerA->id,
        'position' => 'Ketua',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    // Manager B
    $this->managerB = User::factory()->create([
        'email' => 'manager_b@actstation.test',
        'is_active' => true,
    ]);
    $this->managerB->assignRole('community_manager');

    CommunityOfficial::create([
        'community_id' => $this->communityB->id,
        'user_id' => $this->managerB->id,
        'position' => 'Ketua',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    // Internal user (admin)
    $this->internalUser = User::factory()->create([
        'email' => 'staff@actstation.test',
        'is_active' => true,
    ]);
    $this->internalUser->assignRole('staff');

    // Athlete user
    $this->athleteUser = User::factory()->create([
        'email' => 'athlete@actstation.test',
        'is_active' => true,
    ]);
    $this->athleteUser->assignRole('athlete');
});

test('guest diarahkan ke halaman login panel saat mengakses route panel', function (): void {
    $response = $this->get(route('panel.profile.show'));

    $response->assertRedirect(route('panel.login'));
});

test('user internal-only ditolak di panel eksternal dengan status 403', function (): void {
    $response = $this->actingAs($this->internalUser)
        ->get(route('panel.profile.show'));

    $response->assertForbidden();
});

test('manager komunitas A tidak bisa mengakses atau mengubah data komunitas B (403)', function (): void {
    // Official in B
    $officialB = CommunityOfficial::create([
        'community_id' => $this->communityB->id,
        'user_id' => User::factory()->create()->id,
        'position' => 'Sekretaris',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    // Coach in B
    $coachB = CommunityCoach::create([
        'community_id' => $this->communityB->id,
        'user_id' => User::factory()->create()->id,
        'gender_scope' => 'all',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    // Athlete in B
    $athleteUserInB = User::factory()->create();
    $athleteUserInB->assignRole('athlete');
    $athleteB = CommunityAthlete::create([
        'community_id' => $this->communityB->id,
        'user_id' => $athleteUserInB->id,
        'initiated_by' => 'athlete',
        'status' => 'pending',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);

    // Document in B
    $documentB = $this->communityB->documents()->create([
        'category' => 'deed',
        'name' => 'Akta Pendirian B',
        'file_path' => 'community_documents/akta_b.pdf',
        'status' => 'pending',
    ]);
    Storage::disk('local')->put('community_documents/akta_b.pdf', 'Dokumen rahasia B');

    // Invitation in B
    $invitationService = app(InvitationService::class);
    $invB = $invitationService->createInvitation($this->communityB, [], $this->managerB)['invitation'];

    // 1. Manager A cannot approve athlete B
    $this->actingAs($this->managerA)
        ->post(route('panel.athletes.approve', $athleteB))
        ->assertForbidden();

    // 2. Manager A cannot reject athlete B
    $this->actingAs($this->managerA)
        ->post(route('panel.athletes.reject', $athleteB))
        ->assertForbidden();

    // 3. Manager A cannot release athlete B
    $this->actingAs($this->managerA)
        ->post(route('panel.athletes.release', $athleteB))
        ->assertForbidden();

    // 4. Manager A cannot delete official B
    $this->actingAs($this->managerA)
        ->delete(route('panel.officials.destroy', $officialB))
        ->assertForbidden();

    // 5. Manager A cannot delete coach B
    $this->actingAs($this->managerA)
        ->delete(route('panel.coaches.destroy', $coachB))
        ->assertForbidden();

    // 6. Manager A cannot download document B
    $this->actingAs($this->managerA)
        ->get(route('panel.documents.download', $documentB))
        ->assertForbidden();

    // 7. Manager A cannot delete document B
    $this->actingAs($this->managerA)
        ->delete(route('panel.documents.destroy', $documentB))
        ->assertForbidden();

    // 8. Manager A cannot revoke invitation B
    $this->actingAs($this->managerA)
        ->delete(route('panel.invitations.revoke', $invB))
        ->assertForbidden();
});

test('atlet yang sudah aktif di komunitas lain dengan cabor sama ditolak', function (): void {
    $membershipService = app(AthleteMembershipService::class);

    // Make athlete active in Community A (Basketball)
    $athleteA = $membershipService->addOrInviteAthlete(
        $this->communityA,
        $this->athleteUser->id,
        ['joined_at' => now()->toDateString()],
        'community'
    );
    $membershipService->approveByAthlete($athleteA, $this->athleteUser);

    // Try to activate same athlete in Community B (Basketball, same cabor)
    // 1. Athlete pending in Community B -> manager attempts to approve: throws ValidationException
    $athleteInB = CommunityAthlete::create([
        'community_id' => $this->communityB->id,
        'user_id' => $this->athleteUser->id,
        'initiated_by' => 'athlete',
        'status' => 'pending',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);

    expect(fn () => $membershipService->approveByManager($athleteInB))
        ->toThrow(ValidationException::class);

    // 2. Different sport category succeeds
    $communityVolley = Community::factory()->create([
        'sport_category_id' => $this->sportCategory2,
    ]);

    $athleteVolley = $membershipService->addOrInviteAthlete(
        $communityVolley,
        $this->athleteUser->id,
        [],
        'community'
    );
    $membershipService->approveByAthlete($athleteVolley, $this->athleteUser);

    expect($athleteVolley->fresh()->status)->toBe('active');
});

test('token undangan salah expired revoked memberi respons identik (anti-enumeration)', function (): void {
    $invitationService = app(InvitationService::class);

    // 1. Token invalid (tidak pernah ada)
    $randomToken = Str::random(48);

    // 2. Token expired
    $expiredData = $invitationService->createInvitation(
        $this->communityA,
        ['expires_at' => now()->subDay()],
        $this->managerA
    );
    $expiredToken = $expiredData['plain_token'];

    // 3. Token revoked
    $revokedData = $invitationService->createInvitation(
        $this->communityA,
        [],
        $this->managerA
    );
    $revokedToken = $revokedData['plain_token'];
    $invitationService->revokeInvitation($revokedData['invitation']);

    // HTTP GET tests
    $resInvalid = $this->get(route('invitations.public.show', $randomToken));
    $resExpired = $this->get(route('invitations.public.show', $expiredToken));
    $resRevoked = $this->get(route('invitations.public.show', $revokedToken));

    $resInvalid->assertNotFound();
    $resExpired->assertNotFound();
    $resRevoked->assertNotFound();

    // Responses must be completely identical in status and content
    expect($resExpired->getContent())->toBe($resInvalid->getContent());
    expect($resRevoked->getContent())->toBe($resInvalid->getContent());

    // HTTP POST tests
    $dummyPayload = [
        'consent' => '1',
        'name' => 'Tester',
        'email' => 'test@test.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $postInvalid = $this->post(route('invitations.public.submit', $randomToken), $dummyPayload);
    $postExpired = $this->post(route('invitations.public.submit', $expiredToken), $dummyPayload);
    $postRevoked = $this->post(route('invitations.public.submit', $revokedToken), $dummyPayload);

    $postInvalid->assertNotFound();
    $postExpired->assertNotFound();
    $postRevoked->assertNotFound();

    expect($postExpired->getContent())->toBe($postInvalid->getContent());
    expect($postRevoked->getContent())->toBe($postInvalid->getContent());
});

test('honeypot terisi ditolak dan tidak mengubah database', function (): void {
    $invitationService = app(InvitationService::class);
    $invData = $invitationService->createInvitation(
        $this->communityA,
        [],
        $this->managerA
    );
    $token = $invData['plain_token'];

    $usersBefore = User::count();
    $athletesBefore = CommunityAthlete::count();

    $response = $this->post(route('invitations.public.submit', $token), [
        'consent' => '1',
        'name' => 'Spam Bot',
        'email' => 'bot@spammer.org',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'website_address' => 'https://spam-link.xyz', // honeypot
    ]);

    // Silently redirects to login with flash
    $response->assertRedirect(route('panel.login'));
    $response->assertSessionHas('success');

    // Database is untouched
    expect(User::count())->toBe($usersBefore);
    expect(CommunityAthlete::count())->toBe($athletesBefore);
    expect($invData['invitation']->fresh()->status)->toBe('pending');
});

test('token tidak tersimpan plaintext di database', function (): void {
    $invitationService = app(InvitationService::class);
    $result = $invitationService->createInvitation(
        $this->communityA,
        [],
        $this->managerA
    );

    $plainToken = $result['plain_token'];
    $hashedToken = hash('sha256', $plainToken);

    // Plain token MUST NOT exist in DB
    $this->assertDatabaseMissing('community_invitations', [
        'token' => $plainToken,
    ]);

    // Hashed token MUST exist in DB
    $this->assertDatabaseHas('community_invitations', [
        'token' => $hashedToken,
    ]);
});

test('dokumen panel disimpan di disk local privat dan tidak bisa diakses lewat URL publik', function (): void {
    $fakePdf = UploadedFile::fake()->create('dokumen_legal.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($this->managerA)
        ->post(route('panel.documents.store'), [
            'category' => 'deed',
            'name' => 'Akta Komunitas',
            'document_number' => 'AHU-12345',
            'file' => $fakePdf,
        ]);

    $response->assertRedirect(route('panel.documents.index'));

    $document = Document::latest()->first();
    expect($document)->not->toBeNull();

    // Exists in private 'local' disk
    expect(Storage::disk('local')->exists($document->file_path))->toBeTrue();

    // Does NOT exist in 'public' disk
    expect(Storage::disk('public')->exists($document->file_path))->toBeFalse();

    // Download succeeds for manager of this community
    $downloadResponse = $this->actingAs($this->managerA)
        ->get(route('panel.documents.download', $document));

    $downloadResponse->assertOk();

    // Download forbidden for manager of another community
    $this->actingAs($this->managerB)
        ->get(route('panel.documents.download', $document))
        ->assertForbidden();
});

test('dua sumbu roster persetujuan pribadi dan verifikasi wilayah', function (): void {
    $membershipService = app(AthleteMembershipService::class);

    // Sumbu 1: Manager undang atlet -> status pending
    $athlete = $membershipService->addOrInviteAthlete(
        $this->communityA,
        $this->athleteUser->id,
        [],
        'community'
    );

    expect($athlete->status)->toBe('pending');
    expect($athlete->region_status)->toBe('pending');

    // Atlet approve permohonan di panel/my-requests
    $this->actingAs($this->athleteUser)
        ->post(route('panel.my-requests.approve', $athlete))
        ->assertRedirect(route('panel.my-requests.index'));

    $athlete->refresh();
    expect($athlete->status)->toBe('active');
    expect($athlete->region_status)->toBe('pending');
    expect($athlete->responded_at)->not->toBeNull();

    // Release atlet: status jadi left, left_at tercatat, riwayat TIDAK dihapus
    $this->actingAs($this->managerA)
        ->post(route('panel.athletes.release', $athlete))
        ->assertRedirect(route('panel.athletes.index'));

    $athlete->refresh();
    expect($athlete->status)->toBe('left');
    expect($athlete->left_at)->not->toBeNull();
    $this->assertDatabaseHas('community_athletes', ['id' => $athlete->id]);
});

test('registrasi lewat link undangan membuat user ber-role athlete dan status roster pending', function (): void {
    $invitationService = app(InvitationService::class);
    $result = $invitationService->createInvitation(
        $this->communityA,
        ['is_global' => false, 'email' => 'bintang@actstation.test'],
        $this->managerA
    );

    $response = $this->post(route('invitations.public.submit', $result['plain_token']), [
        'consent' => '1',
        'name' => 'Bintang Baru',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'email' => 'bintang@actstation.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'jersey_number' => 23,
        'position' => 'Point Guard',
    ]);

    $response->assertRedirect(route('panel.login'));
    $response->assertSessionHas('success');

    $newUser = User::where('email', 'bintang@actstation.test')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->hasRole('athlete'))->toBeTrue();

    $this->assertDatabaseHas('community_athletes', [
        'community_id' => $this->communityA->id,
        'user_id' => $newUser->id,
        'status' => 'pending',
        'region_status' => 'pending',
        'initiated_by' => 'athlete',
        'jersey_number' => null,
        'position' => 'Point Guard',
    ]);

    // Private invite marked registered
    expect($result['invitation']->fresh()->status)->toBe('registered');
});

test('resend undangan atlet memiliki cooldown 15 menit', function (): void {
    $membershipService = app(AthleteMembershipService::class);

    $athlete = $membershipService->addOrInviteAthlete(
        $this->communityA,
        $this->athleteUser->id,
        [],
        'community'
    );

    // Set updated_at to 20 minutes ago
    $athlete->updated_at = now()->subMinutes(20);
    $athlete->save();

    // First resend succeeds
    $this->actingAs($this->managerA)
        ->post(route('panel.athletes.resend', $athlete))
        ->assertRedirect(route('panel.athletes.index'))
        ->assertSessionHas('success');

    // Immediate second resend (< 15 mins) fails with validation exception
    expect(fn () => $membershipService->resend($athlete->fresh()))
        ->toThrow(ValidationException::class);
});

test('manager approve atlet ber-initiated_by=community ditolak dan status tetap pending', function (): void {
    $membershipService = app(AthleteMembershipService::class);

    // Komunitas mengundang atlet (initiated_by = community)
    $athlete = $membershipService->addOrInviteAthlete(
        $this->communityA,
        $this->athleteUser->id,
        ['joined_at' => now()->toDateString()],
        'community'
    );

    expect($athlete->status)->toBe('pending');
    expect($athlete->initiated_by)->toBe('community');

    // Manager komunitas A mencoba approve lewat panel athletes
    $response = $this->actingAs($this->managerA)
        ->post(route('panel.athletes.approve', $athlete));

    $response->assertSessionHas('error');
    expect($athlete->fresh()->status)->toBe('pending');
});

test('atlet approve permintaannya sendiri initiated_by=athlete ditolak dan status tetap pending', function (): void {
    // Atlet mengajukan diri ke komunitas (initiated_by = athlete)
    $athlete = CommunityAthlete::create([
        'community_id' => $this->communityA->id,
        'user_id' => $this->athleteUser->id,
        'initiated_by' => 'athlete',
        'status' => 'pending',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);

    expect($athlete->status)->toBe('pending');
    expect($athlete->initiated_by)->toBe('athlete');

    // Atlet mencoba approve permintaannya sendiri lewat panel my-requests
    $response = $this->actingAs($this->athleteUser)
        ->post(route('panel.my-requests.approve', $athlete));

    $response->assertSessionHas('error');
    expect($athlete->fresh()->status)->toBe('pending');
});

test('akun tanpa komunitas tidak melihat menu manajemen komunitas dan melihat cta buat komunitas', function (): void {
    $rival = User::factory()->create([
        'email' => 'rival@gmail.com',
        'is_active' => true,
    ]);
    $rival->assignRole('community_manager');

    $response = $this->actingAs($rival)
        ->get(route('panel.community-events.index'));

    $response->assertOk();

    // CTA and available options
    $response->assertSee('Buat Komunitas');
    $response->assertSee('panel/community/create');
    $response->assertSee('Ikuti Turnamen');
    $response->assertSee('Permintaan Saya');

    // Management menus MUST NOT be present
    $response->assertDontSee('Profil Komunitas');
    $response->assertDontSee('Pengurus');
    $response->assertDontSee('Pelatih');
    $response->assertDontSee('Roster Atlet');
    $response->assertDontSee('Dokumen');
    $response->assertDontSee('Undangan');

    // Visibly informs user to create community before registration
    $response->assertSee('Anda belum memiliki komunitas terdaftar');
    $response->assertSee('Buat Komunitas Sekarang');
});

test('akun tanpa komunitas melihat tombol buat komunitas untuk mendaftar dan tidak bisa daftar turnamen', function (): void {
    $rival = User::factory()->create([
        'email' => 'rival2@gmail.com',
        'is_active' => true,
    ]);
    $rival->assignRole('community_manager');

    $event = Event::factory()->create([
        'sport_category_id' => $this->sportCategory1,
        'status' => 'published',
        'registration_open' => true,
    ]);

    EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'KU-18 Putra',
    ]);

    $response = $this->actingAs($rival)
        ->get(route('panel.community-events.index'));

    $response->assertOk();
    $response->assertSee('Buat Komunitas untuk Mendaftar');
    $response->assertDontSee('Daftar Sekarang');

    // Direct access to registration page is denied without community
    $regResponse = $this->actingAs($rival)
        ->get(route('panel.community-events.create', $event->slug));

    $regResponse->assertRedirect(route('panel.community.create'));
    $regResponse->assertSessionHas('info');
});

test('manager komunitas yang sah mempertahankan seluruh menu dan dapat mendaftar event', function (): void {
    $event = Event::factory()->create([
        'sport_category_id' => $this->sportCategory1,
        'status' => 'published',
        'registration_open' => true,
    ]);

    EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'KU-18 Putra',
    ]);

    $response = $this->actingAs($this->managerA)
        ->get(route('panel.community-events.index'));

    $response->assertOk();

    // Full management menus visible
    $response->assertSee('Profil Komunitas');
    $response->assertSee('Pengurus');
    $response->assertSee('Pelatih');
    $response->assertSee('Roster Atlet');
    $response->assertSee('Dokumen');
    $response->assertSee('Undangan');
    $response->assertSee('Ikuti Turnamen');
    $response->assertSee('Permintaan Saya');

    // No CTA Buat Komunitas
    $response->assertDontSee('Buat Komunitas');

    // Registration allowed
    $response->assertDontSee('Anda belum memiliki komunitas terdaftar');
    $response->assertSee('Daftar Sekarang');
});

test('menu organizer kelola event tetap terlihat untuk role organizer', function (): void {
    $organizer = User::factory()->create([
        'email' => 'organizer@actstation.test',
        'is_active' => true,
    ]);
    $organizer->assignRole('organizer');

    $response = $this->actingAs($organizer)
        ->get(route('panel.community-events.index'));

    $response->assertOk();
    $response->assertSee('Kelola Event Saya');
    $response->assertSee('Panel Organizer');
});
