<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    if (Permission::where('name', 'coverage.view')->doesntExist()) {
        $this->seed(RolePermissionSeeder::class);
    }
});

test('unauthenticated or external user blocked with 403 or redirect on coverage map details', function (): void {
    $guestResponse = $this->getJson(route('admin.coverage-map.details', ['type' => 'school', 'id' => 1]));
    $guestResponse->assertUnauthorized();

    $externalUser = User::factory()->create(['is_active' => true]);
    $memberRole = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'public']);
    $externalUser->assignRole($memberRole);

    $extResponse = $this->actingAs($externalUser)->getJson(route('admin.coverage-map.details', ['type' => 'school', 'id' => 1]));
    $extResponse->assertForbidden();

    $internalUserWithoutPermission = User::factory()->create(['is_active' => true]);
    $staffRoleNoPerm = Role::firstOrCreate(['name' => 'internal_no_coverage', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    $internalUserWithoutPermission->assignRole($staffRoleNoPerm);

    $permResponse = $this->actingAs($internalUserWithoutPermission)->getJson(route('admin.coverage-map.details', ['type' => 'school', 'id' => 1]));
    $permResponse->assertForbidden();
});

test('invalid type or unknown id returns 404', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $invalidTypeResponse = $this->actingAs($admin)->getJson(route('admin.coverage-map.details', ['type' => 'invalid_entity', 'id' => 1]));
    $invalidTypeResponse->assertNotFound();

    $unknownIdResponse = $this->actingAs($admin)->getJson(route('admin.coverage-map.details', ['type' => 'school', 'id' => 99999999]));
    $unknownIdResponse->assertNotFound();
});

test('school details resolve master, paired community override, activities and joined events without PII', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $sport1 = DB::table('sport_categories')->insertGetId([
        'name' => 'Basket Uji Detail',
        'slug' => 'basket-uji-detail',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sport2 = DB::table('sport_categories')->insertGetId([
        'name' => 'Futsal Uji Detail',
        'slug' => 'futsal-uji-detail',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $schoolId = DB::table('schools')->insertGetId([
        'npsn' => 'SCH-TEST-99',
        'name' => 'SMA Test Coverage 99',
        'school_level' => 'sma',
        'school_status' => 'negeri',
        'address' => 'Jl. Master Sekolah No. 1',
        'latitude' => -6.20000000,
        'longitude' => 106.81000000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $communityId = DB::table('communities')->insertGetId([
        'name' => 'Klub Basket SMA 99',
        'slug' => 'klub-basket-sma-99',
        'subtype' => 'school',
        'npsn' => 'SCH-TEST-99',
        'sport_category_id' => $sport1,
        'status' => 'verified',
        'latitude' => -6.20500000,
        'longitude' => 106.81500000,
        'address' => 'Jl. Profil Komunitas No. 2',
        'contact_person' => 'Rahasia PIC',
        'contact_phone' => '08123456789',
        'contact_email' => 'pic@school.test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $actId = DB::table('community_activities')->insertGetId([
        'community_id' => $communityId,
        'title' => 'Latihan Rutin Basket SMA 99',
        'description' => 'Latihan rutin sore hari',
        'scheduled_at' => now()->addDays(2),
        'status' => 'scheduled',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $eventId = DB::table('events')->insertGetId([
        'user_id' => $admin->id,
        'title' => 'Kejuaraan Basket Antar Pelajar 2026',
        'slug' => 'kejuaraan-basket-antar-pelajar-2026',
        'sport_category_id' => $sport2,
        'status' => 'published',
        'starts_at' => now()->addMonth(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $categoryId = DB::table('event_categories')->insertGetId([
        'event_id' => $eventId,
        'name' => 'SMA Putra',
        'slug' => 'sma-putra-99',
        'match_type' => '5x5',
        'gender' => 'male',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('event_participants')->insertGetId([
        'event_id' => $eventId,
        'event_category_id' => $categoryId,
        'community_id' => $communityId,
        'registrant_type' => 'community',
        'team_name' => 'Tim Basket SMA 99',
        'status' => 'confirmed',
        'contact_person' => 'PIC Kontingen Rahasia',
        'contact_phone' => '0899999999',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.details', [
        'type' => 'school',
        'id' => $schoolId,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toHaveKeys(['entity', 'sports', 'activities', 'events', 'links']);
    expect($data['entity']['id'])->toBe($schoolId);
    expect($data['entity']['type'])->toBe('school');
    expect($data['entity']['subtype'])->toBe('school_sma');
    expect($data['entity']['npsn'])->toBe('SCH-TEST-99');
    expect($data['entity']['coordinate_source'])->toBe('profile');
    expect($data['entity']['latitude'])->toEqualWithDelta(-6.20500000, 0.0001);
    expect($data['entity']['longitude'])->toEqualWithDelta(106.81500000, 0.0001);

    expect($data['links']['school']['id'])->toBe($schoolId);
    expect($data['links']['community']['id'])->toBe($communityId);
    expect($data['links']['event']['id'])->toBe($eventId);

    $activityNames = collect($data['activities'])->pluck('name')->all();
    expect($activityNames)->toContain('Latihan Rutin Basket SMA 99');

    $eventTitles = collect($data['events'])->pluck('title')->all();
    expect($eventTitles)->toContain('Kejuaraan Basket Antar Pelajar 2026');

    $sportIds = collect($data['sports'])->pluck('id')->all();
    expect($sportIds)->toContain($sport1);
    expect($sportIds)->toContain($sport2);

    $rawJson = $response->getContent();
    expect($rawJson)->not->toContain('Rahasia PIC');
    expect($rawJson)->not->toContain('08123456789');
    expect($rawJson)->not->toContain('pic@school.test');
    expect($rawJson)->not->toContain('PIC Kontingen Rahasia');
});

test('markers endpoint deduplicates registered school and community when both layers active', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $schoolId = DB::table('schools')->insertGetId([
        'npsn' => 'SCH-DEDUPE-1',
        'name' => 'SMK Dedupe School',
        'school_level' => 'smk',
        'school_status' => 'swasta',
        'latitude' => -6.50000000,
        'longitude' => 106.70000000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $commId = DB::table('communities')->insertGetId([
        'name' => 'Komunitas SMK Dedupe',
        'slug' => 'komunitas-smk-dedupe',
        'subtype' => 'school',
        'npsn' => 'SCH-DEDUPE-1',
        'status' => 'verified',
        'latitude' => -6.50000000,
        'longitude' => 106.70000000,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $respBoth = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'bbox' => '-6.60,106.60,-6.40,106.80',
        'layers' => 'school,verified_comm',
    ]));

    $respBoth->assertOk();
    $bothMarkers = $respBoth->json('data');

    $schoolHits = collect($bothMarkers)->filter(fn ($m) => ($m['npsn'] ?? null) === 'SCH-DEDUPE-1')->all();
    expect(count($schoolHits))->toBe(1);
    expect($schoolHits[0]['type'])->toBe('school');
    expect($schoolHits[0]['community_id'])->toBe($commId);
    expect($schoolHits[0]['entity_subtype'])->toBe('school_sma');

    $respOnlyComm = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'bbox' => '-6.60,106.60,-6.40,106.80',
        'layers' => 'verified_comm',
    ]));

    $respOnlyComm->assertOk();
    $commOnlyMarkers = $respOnlyComm->json('data');
    $commHits = collect($commOnlyMarkers)->filter(fn ($m) => ($m['npsn'] ?? null) === 'SCH-DEDUPE-1')->all();
    expect(count($commHits))->toBe(1);
    expect($commHits[0]['type'])->toBe('verified_comm');
    expect($commHits[0]['community_id'])->toBe($commId);
});

test('cabor filter only includes linked entities coverage and accepts legacy school level aliases', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $caborTarget = DB::table('sport_categories')->insertGetId([
        'name' => 'Voli Filter Test',
        'slug' => 'voli-filter-test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $caborOther = DB::table('sport_categories')->insertGetId([
        'name' => 'Badminton Filter Test',
        'slug' => 'badminton-filter-test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $linkedSchoolId = DB::table('schools')->insertGetId([
        'npsn' => 'SCH-LINKED-CABOR',
        'name' => 'SMP Linked Cabor',
        'school_level' => 'smp',
        'school_status' => 'negeri',
        'latitude' => -6.15000000,
        'longitude' => 106.85000000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('communities')->insertGetId([
        'name' => 'Komunitas Voli SMP Linked',
        'slug' => 'komunitas-voli-smp-linked',
        'subtype' => 'school',
        'npsn' => 'SCH-LINKED-CABOR',
        'sport_category_id' => $caborTarget,
        'status' => 'verified',
        'latitude' => -6.15000000,
        'longitude' => 106.85000000,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $unlinkedSchoolId = DB::table('schools')->insertGetId([
        'npsn' => 'SCH-UNLINKED-CABOR',
        'name' => 'SMP Unlinked Master School',
        'school_level' => 'smp',
        'school_status' => 'negeri',
        'latitude' => -6.16000000,
        'longitude' => 106.86000000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.coverage-map.markers', [
        'bbox' => '-6.20,106.80,-6.10,106.90',
        'layers' => 'school',
        'school_level' => 'school_smp',
        'sport_category_id' => $caborTarget,
    ]));

    $response->assertOk();
    $data = $response->json('data');

    $ids = collect($data)->pluck('id')->all();
    expect($ids)->toContain($linkedSchoolId);
    expect($ids)->not->toContain($unlinkedSchoolId);
});

test('coverage map index page renders without url generation exception and details supports query params', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $indexResponse = $this->actingAs($admin)->get(route('admin.coverage-map.index'));
    $indexResponse->assertOk();

    $queryParamResponse = $this->actingAs($admin)->getJson(route('admin.coverage-map.details', [
        'type' => 'school',
        'id' => 99999999,
    ]));
    $queryParamResponse->assertNotFound();

    $queryUrlResponse = $this->actingAs($admin)->getJson('/admin/coverage-map/details?type=school&id=99999999');
    $queryUrlResponse->assertNotFound();
});
