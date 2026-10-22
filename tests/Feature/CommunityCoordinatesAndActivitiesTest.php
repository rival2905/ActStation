<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityActivity;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\MasterData\Models\School;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'],
        ['kode' => '32.73', 'nama' => 'Kota Bandung'],
    ]);

    // Active Sport Categories
    $this->sportBasketball = DB::table('sport_categories')->insertGetId([
        'name' => 'Basketball',
        'slug' => 'basketball-'.Str::random(5),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->sportVolleyball = DB::table('sport_categories')->insertGetId([
        'name' => 'Volleyball',
        'slug' => 'volleyball-'.Str::random(5),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Inactive Sport Category
    $this->sportInactive = DB::table('sport_categories')->insertGetId([
        'name' => 'Curling',
        'slug' => 'curling-'.Str::random(5),
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Master School
    $this->school = School::create([
        'npsn' => '20100001',
        'name' => 'SMAN 1 Juara',
        'school_level' => 'sma',
        'school_status' => 'negeri',
        'province_id' => '32',
        'city_id' => '32.73',
        'address' => 'Jl. Pahlawan Olahraga No. 1',
        'latitude' => -6.91474400,
        'longitude' => 107.60981000,
    ]);

    // Community A
    $this->communityA = Community::factory()->create([
        'name' => 'Komunitas Elang Mas',
        'sport_category_id' => $this->sportBasketball,
        'province_id' => '32',
        'city_id' => '32.73',
        'latitude' => -6.91746400,
        'longitude' => 107.61912300,
    ]);

    // Community B
    $this->communityB = Community::factory()->create([
        'name' => 'Komunitas Garuda Sakti',
        'sport_category_id' => $this->sportBasketball,
        'province_id' => '32',
        'city_id' => '32.73',
        'latitude' => -6.92000000,
        'longitude' => 107.62000000,
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
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
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
        'position' => 'manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
        'ended_at' => null,
    ]);

    // Regular user without official role
    $this->unauthorizedUser = User::factory()->create([
        'email' => 'regular@actstation.test',
        'is_active' => true,
    ]);
    $this->unauthorizedUser->assignRole('community_manager');
});

test('guest tidak terautentikasi dialihkan ke login panel saat mengakses rute koordinat dan program rutin', function (): void {
    $this->get(route('panel.profile.show'))->assertRedirect(route('panel.login'));
    $this->post(route('panel.activities.store'), [
        'sport_category_id' => $this->sportBasketball,
        'title' => 'Program Basket',
    ])->assertRedirect(route('panel.login'));
});

test('user tanpa status official aktif ditolak 403 saat mengakses profil dan program aktivitas', function (): void {
    $this->actingAs($this->unauthorizedUser)
        ->post(route('panel.activities.store'), [
            'sport_category_id' => $this->sportBasketball,
            'title' => 'Program Futsal',
        ])
        ->assertForbidden();
});

test('update koordinat bernilai nol 0 adalah valid dan tersimpan', function (): void {
    $response = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $this->communityA->name,
            'province_id' => '32',
            'city_id' => '32.73',
            'latitude' => 0,
            'longitude' => 0,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('panel.profile.show'));

    $fresh = $this->communityA->fresh();
    expect((float) $fresh->latitude)->toBe(0.0)
        ->and((float) $fresh->longitude)->toBe(0.0);
});

test('update koordinat di luar rentang angka ditolak 422', function (): void {
    // Latitude > 90
    $responseLat = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $this->communityA->name,
            'province_id' => '32',
            'city_id' => '32.73',
            'latitude' => 95.0,
            'longitude' => 107.6,
        ]);

    $responseLat->assertSessionHasErrors(['latitude']);

    // Longitude > 180
    $responseLng = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $this->communityA->name,
            'province_id' => '32',
            'city_id' => '32.73',
            'latitude' => -6.9,
            'longitude' => 185.0,
        ]);

    $responseLng->assertSessionHasErrors(['longitude']);
});

test('koordinat parsial hanya satu field dikirim ditolak 422', function (): void {
    // Only latitude sent
    $response1 = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $this->communityA->name,
            'province_id' => '32',
            'city_id' => '32.73',
            'latitude' => -6.9,
        ]);

    $response1->assertSessionHasErrors(['longitude']);

    // Only longitude sent
    $response2 = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $this->communityA->name,
            'province_id' => '32',
            'city_id' => '32.73',
            'longitude' => 107.6,
        ]);

    $response2->assertSessionHasErrors(['latitude']);

    // Latitude with explicit null longitude
    $response3 = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $this->communityA->name,
            'province_id' => '32',
            'city_id' => '32.73',
            'latitude' => -6.9,
            'longitude' => null,
        ]);

    $response3->assertSessionHasErrors(['longitude']);
});

test('penghapusan eksplisit kedua koordinat null diizinkan', function (): void {
    expect($this->communityA->latitude)->not->toBeNull();
    expect($this->communityA->longitude)->not->toBeNull();

    $response = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => $this->communityA->name,
            'province_id' => '32',
            'city_id' => '32.73',
            'latitude' => null,
            'longitude' => null,
        ]);

    $response->assertSessionHasNoErrors();
    $fresh = $this->communityA->fresh();
    expect($fresh->latitude)->toBeNull()
        ->and($fresh->longitude)->toBeNull();
});

test('koordinat komunitas dipertahankan saat field koordinat diabaikan / omitted', function (): void {
    $origLat = (float) $this->communityA->latitude;
    $origLng = (float) $this->communityA->longitude;

    // Request does not send latitude or longitude keys at all
    $response = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->put(route('panel.profile.update'), [
            'name' => 'Nama Baru Komunitas Elang Mas',
            'province_id' => '32',
            'city_id' => '32.73',
        ]);

    $response->assertSessionHasNoErrors();
    $fresh = $this->communityA->fresh();
    expect($fresh->name)->toBe('Nama Baru Komunitas Elang Mas')
        ->and((float) $fresh->latitude)->toBe($origLat)
        ->and((float) $fresh->longitude)->toBe($origLng);
});

test('pendaftaran sekolah tanpa koordinat fallback ke koordinat master sekolah lokal', function (): void {
    $newUser = User::factory()->create(['is_active' => true]);
    $newUser->assignRole('community_manager');

    $response = $this->actingAs($newUser)
        ->post(route('panel.community.store'), [
            'name' => 'Sekolah Bebas',
            'subtype' => 'school_sma',
            'npsn' => $this->school->npsn,
            'sport_category_id' => $this->sportBasketball,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Budi Guru',
            'contact_phone' => '081234567890',
            'contact_email' => 'guru@sman1.sch.id',
        ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $community = Community::where('npsn', $this->school->npsn)->first();
    expect($community)->not->toBeNull()
        ->and((float) $community->latitude)->toBe((float) $this->school->latitude)
        ->and((float) $community->longitude)->toBe((float) $this->school->longitude);

    // Master school records in schools table are untouched
    $master = $this->school->fresh();
    expect((float) $master->latitude)->toBe((float) $this->school->latitude)
        ->and((float) $master->longitude)->toBe((float) $this->school->longitude);
});

test('pendaftaran sekolah dengan koordinat kustom tidak ditimpa oleh koordinat master sekolah', function (): void {
    $newUser = User::factory()->create(['is_active' => true]);
    $newUser->assignRole('community_manager');

    $response = $this->actingAs($newUser)
        ->post(route('panel.community.store'), [
            'name' => 'Sekolah Bebas',
            'subtype' => 'school_sma',
            'npsn' => $this->school->npsn,
            'sport_category_id' => $this->sportBasketball,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_person' => 'Budi Guru',
            'contact_phone' => '081234567890',
            'contact_email' => 'guru@sman1.sch.id',
            'latitude' => -6.12345600,
            'longitude' => 106.98765400,
        ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $community = Community::where('npsn', $this->school->npsn)->first();
    expect($community)->not->toBeNull()
        ->and((float) $community->latitude)->toBe(-6.12345600)
        ->and((float) $community->longitude)->toBe(106.98765400);

    // Master school remains untouched
    $master = $this->school->fresh();
    expect((float) $master->latitude)->toBe((float) $this->school->latitude);
});

test('entitas komunitas dapat memiliki multi-sport program rutin lintas cabang olahraga', function (): void {
    // Program 1: Basketball
    $resp1 = $this->actingAs($this->managerA)
        ->post(route('panel.activities.store'), [
            'sport_category_id' => $this->sportBasketball,
            'title' => 'Ekstrakurikuler Basket Putra',
            'description' => 'Latihan rutin KU-18',
            'schedule' => 'Senin & Rabu 16:00 - 18:00 WIB',
            'location' => 'GOR Pajajaran',
            'is_active' => true,
        ]);

    $resp1->assertRedirect(route('panel.profile.show'));

    // Program 2: Volleyball (different sport category)
    $resp2 = $this->actingAs($this->managerA)
        ->post(route('panel.activities.store'), [
            'sport_category_id' => $this->sportVolleyball,
            'title' => 'Program Ekstrakurikuler Bola Voli',
            'description' => 'Latihan rutin tim putri',
            'schedule' => 'Selasa & Kamis 15:30 - 17:30 WIB',
            'location' => 'Lapangan Voli Outdoor',
            'is_active' => true,
        ]);

    $resp2->assertRedirect(route('panel.profile.show'));

    // Verify both activities exist for community A
    $activities = CommunityActivity::where('community_id', $this->communityA->id)->get();
    expect($activities)->toHaveCount(2);

    $cabors = $activities->pluck('sport_category_id')->all();
    expect($cabors)->toContain($this->sportBasketball)
        ->and($cabors)->toContain($this->sportVolleyball);

    // Legacy community sport_category_id is preserved
    expect($this->communityA->fresh()->sport_category_id)->toBe($this->sportBasketball);
});

test('penolakan program aktivitas dengan sport category tidak aktif atau tidak ditemukan', function (): void {
    // Inactive sport category
    $respInactive = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->post(route('panel.activities.store'), [
            'sport_category_id' => $this->sportInactive,
            'title' => 'Program Curling',
            'schedule' => 'Jumat 14:00 WIB',
        ]);

    $respInactive->assertSessionHasErrors(['sport_category_id']);

    // Non-existent foreign sport category
    $respNotFound = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->post(route('panel.activities.store'), [
            'sport_category_id' => 999999,
            'title' => 'Program Misterius',
        ]);

    $respNotFound->assertSessionHasErrors(['sport_category_id']);

    // Arbitrary JSON / string sport category
    $respString = $this->actingAs($this->managerA)
        ->from(route('panel.profile.show'))
        ->post(route('panel.activities.store'), [
            'sport_category_id' => '{"id": 1}',
            'title' => 'Program Injeksi',
        ]);

    $respString->assertSessionHasErrors(['sport_category_id']);
});

test('manager komunitas A tidak dapat memperbarui atau menghapus aktivitas milik komunitas B (403)', function (): void {
    $activityB = CommunityActivity::create([
        'community_id' => $this->communityB->id,
        'sport_category_id' => $this->sportBasketball,
        'title' => 'Latihan Komunitas B',
        'schedule' => 'Sabtu Pagi',
        'is_active' => true,
    ]);

    // Manager A cannot update activity of B
    $this->actingAs($this->managerA)
        ->put(route('panel.activities.update', $activityB), [
            'sport_category_id' => $this->sportBasketball,
            'title' => 'Update Bajakan',
        ])
        ->assertForbidden();

    expect($activityB->fresh()->title)->toBe('Latihan Komunitas B');

    // Manager A cannot delete activity of B
    $this->actingAs($this->managerA)
        ->delete(route('panel.activities.destroy', $activityB))
        ->assertForbidden();

    $this->assertModelExists($activityB);
});

test('halaman profil panel menyediakan data koleksi activities beserta sport_name dan activitySportCategories', function (): void {
    CommunityActivity::create([
        'community_id' => $this->communityA->id,
        'sport_category_id' => $this->sportBasketball,
        'title' => 'Basket Rutin A',
        'schedule' => 'Senin 16:00',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->managerA)->get(route('panel.profile.show'));

    $response->assertOk();
    $response->assertViewHas('activities');
    $response->assertViewHas('activitySportCategories');

    $activities = $response->viewData('activities');
    expect($activities)->toHaveCount(1)
        ->and($activities->first()->sport_name)->toBe('Basketball')
        ->and($activities->first()->title)->toBe('Basket Rutin A');
});
