<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\CommunityLookup;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityActivity;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');

    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '32.73', 'nama' => 'Kota Bandung'],
    ]);

    $this->sportCategory = DB::table('sport_categories')->insertGetId([
        'name' => 'Basketball',
        'slug' => 'basketball-'.Str::random(5),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Fixture: Authorized manager with 3 entities (two communities + one club)
    $this->manager = User::factory()->create([
        'name' => 'Multi Manager',
        'email' => 'manager_multi@actstation.test',
        'is_active' => true,
    ]);
    $this->manager->assignRole('community_manager');

    $this->communityClub = Community::factory()->create([
        'name' => 'Klub Singa Juara',
        'subtype' => 'club',
        'sport_category_id' => $this->sportCategory,
        'province_id' => '32',
        'city_id' => '32.73',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->communitySchool = Community::factory()->create([
        'name' => 'SMA Harapan Bangsa',
        'subtype' => 'school',
        'sport_category_id' => $this->sportCategory,
        'province_id' => '32',
        'city_id' => '32.73',
        'status' => 'draft',
        'is_active' => true,
    ]);

    $this->communityInstitution = Community::factory()->create([
        'name' => 'Dispora Jaya Mandiri',
        'subtype' => 'institution',
        'institution_type' => 'government_agency',
        'sport_category_id' => $this->sportCategory,
        'province_id' => '32',
        'city_id' => '32.73',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->officialClub = CommunityOfficial::create([
        'community_id' => $this->communityClub->id,
        'user_id' => $this->manager->id,
        'position' => 'Ketua Klub',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    $this->officialSchool = CommunityOfficial::create([
        'community_id' => $this->communitySchool->id,
        'user_id' => $this->manager->id,
        'position' => 'Koordinator Olahraga',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    $this->officialInstitution = CommunityOfficial::create([
        'community_id' => $this->communityInstitution->id,
        'user_id' => $this->manager->id,
        'position' => 'Penanggung Jawab',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    // Another unrelated community
    $this->unrelatedCommunity = Community::factory()->create([
        'name' => 'Klub Lain Asing',
        'subtype' => 'club',
        'sport_category_id' => $this->sportCategory,
        'status' => 'verified',
        'is_active' => true,
    ]);
});

test('guest cannot access panel switch and is redirected to login', function (): void {
    $response = $this->post(route('panel.switch'), [
        'community_id' => $this->communityClub->id,
    ]);

    $response->assertRedirect();
});

test('authorized manager can switch between 3 authorized entities safely', function (): void {
    // 1. Default first active community is communityClub
    $this->actingAs($this->manager);

    $lookup = app(CommunityLookup::class);
    expect($lookup->findForUser($this->manager->id)?->id)->toBe($this->communityClub->id);

    // 2. Switch to School
    $res1 = $this->post(route('panel.switch'), [
        'community_id' => $this->communitySchool->id,
    ]);
    $res1->assertRedirect(route('panel.profile.show'));
    $res1->assertSessionHas('current_community_id', $this->communitySchool->id);

    $this->get(route('panel.profile.show'))
        ->assertOk()
        ->assertSee('SMA Harapan Bangsa');

    expect($lookup->findForUser($this->manager->id)?->id)->toBe($this->communitySchool->id);

    // 3. Switch to Institution
    $res2 = $this->post(route('panel.switch'), [
        'community_id' => $this->communityInstitution->id,
    ]);
    $res2->assertRedirect(route('panel.profile.show'));
    $res2->assertSessionHas('current_community_id', $this->communityInstitution->id);

    $this->get(route('panel.profile.show'))
        ->assertOk()
        ->assertSee('Dispora Jaya Mandiri');

    expect($lookup->findForUser($this->manager->id)?->id)->toBe($this->communityInstitution->id);
});

test('switching community scopes view, update, documents, and activities with no cross-owner leakage', function (): void {
    $this->actingAs($this->manager);

    // Switch to Club
    $this->post(route('panel.switch'), ['community_id' => $this->communityClub->id]);

    // Update profile on Club
    $updateResponse = $this->put(route('panel.profile.update'), [
        'name' => 'Klub Singa Juara Perkasa',
        'subtype' => 'club',
        'sport_category_id' => $this->sportCategory,
        'province_id' => '32',
        'city_id' => '32.73',
    ]);
    $updateResponse->assertRedirect(route('panel.profile.show'));

    // Verify Club was updated and School was untouched
    expect($this->communityClub->fresh()->name)->toBe('Klub Singa Juara Perkasa')
        ->and($this->communitySchool->fresh()->name)->toBe('SMA Harapan Bangsa');

    // Switch to School
    $this->post(route('panel.switch'), ['community_id' => $this->communitySchool->id]);

    // Add activity to School
    $activityResponse = $this->post(route('panel.activities.store'), [
        'sport_category_id' => $this->sportCategory,
        'title' => 'Latihan Rutin Basket SMA',
        'schedule' => 'Setiap Rabu',
        'location' => 'Lapangan SMA',
        'is_active' => 1,
    ]);
    $activityResponse->assertRedirect(route('panel.profile.show'));

    $activity = CommunityActivity::where('title', 'Latihan Rutin Basket SMA')->first();
    expect($activity)->not->toBeNull()
        ->and((int) $activity->community_id)->toBe($this->communitySchool->id);

    // Upload Document to School
    $docFile = UploadedFile::fake()->create('surat_tugas.pdf', 200, 'application/pdf');
    $docResponse = $this->post(route('panel.documents.store'), [
        'category' => 'other',
        'name' => 'Surat Tugas Sekolah',
        'file' => $docFile,
    ]);
    $docResponse->assertRedirect(route('panel.documents.index'));

    $doc = Document::where('name', 'Surat Tugas Sekolah')->first();
    expect($doc)->not->toBeNull()
        ->and($doc->documentable_type)->toBe(Community::class)
        ->and((int) $doc->documentable_id)->toBe($this->communitySchool->id);
});

test('forged switch target without official role returns 403 and invalid id returns 403 or 422', function (): void {
    $this->actingAs($this->manager);

    // Forged target: user is NOT official of unrelatedCommunity
    $resForbidden = $this->post(route('panel.switch'), [
        'community_id' => $this->unrelatedCommunity->id,
    ]);
    $resForbidden->assertForbidden();

    // Invalid non-existent target
    $resNotFound = $this->post(route('panel.switch'), [
        'community_id' => 999999,
    ]);
    expect(in_array($resNotFound->status(), [403, 422], true))->toBeTrue();
});

test('admin can switch to any active community override', function (): void {
    $admin = User::factory()->create(['email' => 'admin_test@actstation.test', 'is_active' => true]);
    $admin->assignRole('superadmin');

    $this->actingAs($admin);

    $res = $this->post(route('panel.switch'), [
        'community_id' => $this->unrelatedCommunity->id,
    ]);
    $res->assertRedirect(route('panel.profile.show'));
    $res->assertSessionHas('current_community_id', $this->unrelatedCommunity->id);
});

test('revoked, inactive, or deleted session selection falls back safely to valid active community', function (): void {
    $this->actingAs($this->manager);

    // 1. Inactive community in session
    $this->withSession(['current_community_id' => $this->communitySchool->id]);
    $this->communitySchool->update(['is_active' => false]);

    // Accessing profile falls back to active club
    $this->get(route('panel.profile.show'))
        ->assertOk()
        ->assertSee('Klub Singa Juara');

    // 2. Soft-deleted community in session
    $this->communitySchool->update(['is_active' => true]);
    $this->communitySchool->delete();

    $this->withSession(['current_community_id' => $this->communitySchool->id]);
    $this->get(route('panel.profile.show'))
        ->assertOk()
        ->assertSee('Klub Singa Juara');

    // 3. Revoked / ended official status in session
    $this->officialInstitution->update(['status' => 'inactive', 'ended_at' => now()]);
    $this->withSession(['current_community_id' => $this->communityInstitution->id]);

    $this->get(route('panel.profile.show'))
        ->assertOk()
        ->assertSee('Klub Singa Juara');
});

test('stale form token rejects mutation with 422 and prevents unintended updates', function (): void {
    $this->actingAs($this->manager);

    // Current session is on Institution
    $this->withSession(['current_community_id' => $this->communityInstitution->id]);

    // Form was loaded when user was on School (stale token = communitySchool->id)
    $staleResponse = $this->post(route('panel.activities.store'), [
        'panel_community_id' => $this->communitySchool->id, // Mismatch!
        'sport_category_id' => $this->sportCategory,
        'title' => 'Stale Program Mutasi Liar',
        'schedule' => 'Senin',
        'location' => 'Lokasi Fiktif',
    ]);

    $staleResponse->assertStatus(422);

    // Verify NO activity was created
    expect(CommunityActivity::where('title', 'Stale Program Mutasi Liar')->exists())->toBeFalse();

    // Valid token matching current community succeeds
    $validResponse = $this->post(route('panel.activities.store'), [
        'panel_community_id' => $this->communityInstitution->id,
        'sport_category_id' => $this->sportCategory,
        'title' => 'Program Sah Sesuai Konteks',
        'schedule' => 'Senin',
        'location' => 'Lokasi Sah',
        'is_active' => 1,
    ]);
    $validResponse->assertRedirect(route('panel.profile.show'));
    expect(CommunityActivity::where('title', 'Program Sah Sesuai Konteks')->exists())->toBeTrue();
});

test('header dropdown renders switcher for multiple entities, label for single, and create for zero', function (): void {
    // 1. Manager with 3 entities: shows switcher dropdown
    $this->actingAs($this->manager);
    $viewMulti = $this->view('layouts.partials.panel-header');
    $viewMulti->assertSee('id="entitySwitcherDropdown"', false)
        ->assertSee('Klub Singa Juara')
        ->assertSee('SMA Harapan Bangsa')
        ->assertSee('Dispora Jaya Mandiri')
        ->assertSee(route('panel.switch'), false);

    // 2. User with 1 entity: shows current label, no switcher dropdown
    $singleManager = User::factory()->create(['email' => 'single@actstation.test', 'is_active' => true]);
    $singleManager->assignRole('community_manager');
    CommunityOfficial::create([
        'community_id' => $this->communityClub->id,
        'user_id' => $singleManager->id,
        'position' => 'Staf Tunggal',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    $this->actingAs($singleManager);
    $viewSingle = $this->view('layouts.partials.panel-header');
    $viewSingle->assertDontSee('id="entitySwitcherDropdown"', false)
        ->assertSee('Klub Singa Juara');

    // 3. User with 0 entities: shows Buat Komunitas
    $zeroManager = User::factory()->create(['email' => 'zero@actstation.test', 'is_active' => true]);
    $this->actingAs($zeroManager);
    $viewZero = $this->view('layouts.partials.panel-header');
    $viewZero->assertDontSee('id="entitySwitcherDropdown"', false)
        ->assertSee('Buat Komunitas');
});
