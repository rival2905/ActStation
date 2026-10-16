<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Services\InvitationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $sportCategoryId = DB::table('sport_categories')->insertGetId([
        'name' => 'Basketball',
        'slug' => 'basketball-'.Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->community = Community::factory()->create([
        'name' => 'Klub Elang Basket',
        'sport_category_id' => $sportCategoryId,
    ]);

    $this->manager = User::factory()->create([
        'email' => 'manager_elang@actstation.test',
        'is_active' => true,
    ]);
    $this->manager->assignRole('community_manager');
    CommunityOfficial::factory()->create([
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'position' => 'Manager',
    ]);
});

test('manager can create global athlete link with expires_days and view copyable url in panel', function (): void {
    $response = $this->actingAs($this->manager)
        ->withSession(['current_community_id' => $this->community->id])
        ->post(route('panel.invitations.store'), [
            'is_global' => 1,
            'invite_type' => 'athlete',
            'max_registrations' => 50,
            'expires_days' => 30,
        ]);

    $response->assertRedirect(route('panel.athletes.index'));
    $response->assertSessionHas('invite_url');
    $inviteUrl = session('invite_url');
    expect($inviteUrl)->not->toBeNull();

    $invitation = $this->community->invitations()->first();
    expect($invitation->is_global)->toBeTrue()
        ->and($invitation->max_registrations)->toBe(50)
        ->and($invitation->expires_at)->not->toBeNull()
        ->and($invitation->invitation_url)->toBe($inviteUrl);

    // Visiting panel athletes displays the Link Global Atlet section and copyable URL
    $indexResponse = $this->actingAs($this->manager)
        ->withSession(['current_community_id' => $this->community->id])
        ->get(route('panel.athletes.index'));

    $indexResponse->assertOk();
    $indexResponse->assertSee('Link Global Atlet');
    $indexResponse->assertSee($inviteUrl);
    $indexResponse->assertSee('50 pendaftar');

    // Global link is segregated into globalLinks and does not pollute private invitations tab
    expect($indexResponse->viewData('globalLinks'))->toHaveCount(1)
        ->and($indexResponse->viewData('invitations'))->toHaveCount(0);
});

test('incoming registrations via link global appear in pendaftaran atlet baru and can be approved', function (): void {
    // 1. Create global invitation
    $invitationService = app(InvitationService::class);
    $result = $invitationService->createInvitation($this->community, [
        'is_global' => true,
        'invite_type' => 'athlete',
        'max_registrations' => 10,
    ], $this->manager);

    // 2. Athlete registers through link global
    $province = DB::table('wilayah')->insertGetId([
        'kode' => '32',
        'nama' => 'JAWA BARAT',
    ]);
    $city = DB::table('wilayah')->insertGetId([
        'kode' => '32.73',
        'nama' => 'KOTA BANDUNG',
    ]);

    $athleteUser = User::factory()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi.santoso@actstation.test',
        'phone_number' => '081234567890',
        'birth_date' => '2008-05-15',
        'province_id' => '32',
        'city_id' => '32.73',
        'is_active' => true,
    ]);
    $athleteUser->assignRole('athlete');

    $membership = CommunityAthlete::create([
        'community_id' => $this->community->id,
        'user_id' => $athleteUser->id,
        'status' => 'pending',
        'initiated_by' => 'athlete',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);

    // 3. Panel athletes shows Pendaftaran Atlet Baru card with athlete data
    $indexResponse = $this->actingAs($this->manager)
        ->withSession(['current_community_id' => $this->community->id])
        ->get(route('panel.athletes.index'));

    $indexResponse->assertOk();
    $indexResponse->assertSee('Pendaftaran Atlet Baru');
    $indexResponse->assertSee('Budi Santoso');
    $indexResponse->assertSee('KOTA BANDUNG');
    $indexResponse->assertSee('JAWA BARAT');
    $indexResponse->assertSee('Setujui');
    $indexResponse->assertSee('Tolak');

    // 4. Manager approves the registration
    $approveResponse = $this->actingAs($this->manager)
        ->withSession(['current_community_id' => $this->community->id])
        ->post(route('panel.athletes.approve', $membership));

    $approveResponse->assertRedirect(route('panel.athletes.index'));
    expect($membership->fresh()->status)->toBe('active');
});

test('manager can reject incoming athlete registration from link global', function (): void {
    $athleteUser = User::factory()->create([
        'name' => 'Calon Atlet Ditolak',
        'email' => 'calon.ditolak@actstation.test',
        'is_active' => true,
    ]);
    $athleteUser->assignRole('athlete');

    $membership = CommunityAthlete::create([
        'community_id' => $this->community->id,
        'user_id' => $athleteUser->id,
        'status' => 'pending',
        'initiated_by' => 'athlete',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);

    $rejectResponse = $this->actingAs($this->manager)
        ->withSession(['current_community_id' => $this->community->id])
        ->post(route('panel.athletes.reject', $membership));

    $rejectResponse->assertRedirect(route('panel.athletes.index'));
    expect($membership->fresh()->status)->toBe('rejected');
});

test('manager can revoke active global link and it disappears from active list', function (): void {
    $this->actingAs($this->manager)
        ->withSession(['current_community_id' => $this->community->id])
        ->post(route('panel.invitations.store'), [
            'is_global' => 1,
            'invite_type' => 'athlete',
            'max_registrations' => 20,
            'expires_days' => 14,
        ]);

    $invitation = $this->community->invitations()->first();
    expect($invitation->status)->toBe('pending');

    $deleteResponse = $this->actingAs($this->manager)
        ->withSession(['current_community_id' => $this->community->id])
        ->delete(route('panel.invitations.revoke', $invitation));

    $deleteResponse->assertRedirect(route('panel.athletes.index'));
    expect($invitation->fresh()->status)->toBe('revoked');

    $indexResponse = $this->actingAs($this->manager)
        ->withSession(['current_community_id' => $this->community->id])
        ->get(route('panel.athletes.index'));

    $indexResponse->assertOk();
    // Card Link Global Atlet is not displayed when no active global links exist
    $indexResponse->assertDontSee('link aktif');
    $indexResponse->assertDontSee($invitation->invitation_url);
    expect($indexResponse->viewData('globalLinks')->isEmpty())->toBeTrue();
});
