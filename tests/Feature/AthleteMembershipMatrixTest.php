<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Services\AthleteMembershipService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->basketball = DB::table('sport_categories')->insertGetId([
        'name' => 'Bola Basket Matrix',
        'slug' => 'bola-basket-matrix',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->futsal = DB::table('sport_categories')->insertGetId([
        'name' => 'Futsal Matrix',
        'slug' => 'futsal-matrix',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->membershipService = app(AthleteMembershipService::class);
});

test('club-club rejection: atlet aktif di klub A cabor basket tidak dapat aktif di klub B cabor basket', function (): void {
    $athleteUser = User::factory()->create(['is_active' => true]);

    $clubA = Community::factory()->create([
        'subtype' => 'club',
        'sport_category_id' => $this->basketball,
        'status' => 'verified',
    ]);

    $clubB = Community::factory()->create([
        'subtype' => 'club',
        'sport_category_id' => $this->basketball,
        'status' => 'verified',
    ]);

    CommunityAthlete::factory()->create([
        'community_id' => $clubA->id,
        'user_id' => $athleteUser->id,
        'status' => 'active',
        'left_at' => null,
    ]);

    $pendingB = CommunityAthlete::factory()->create([
        'community_id' => $clubB->id,
        'user_id' => $athleteUser->id,
        'status' => 'pending',
        'left_at' => null,
    ]);

    expect(fn () => $this->membershipService->assertNoActiveDualMembership($athleteUser->id, $clubB, $pendingB->id))
        ->toThrow(ValidationException::class);
});

test('club+school acceptance: atlet aktif di klub dapat aktif di komunitas sekolah cabor sama', function (): void {
    $athleteUser = User::factory()->create(['is_active' => true]);

    $clubA = Community::factory()->create([
        'subtype' => 'club',
        'sport_category_id' => $this->basketball,
        'status' => 'verified',
    ]);

    $school = Community::factory()->create([
        'subtype' => 'school',
        'sport_category_id' => $this->basketball,
        'npsn' => '20199999',
        'status' => 'verified',
    ]);

    CommunityAthlete::factory()->create([
        'community_id' => $clubA->id,
        'user_id' => $athleteUser->id,
        'status' => 'active',
        'left_at' => null,
    ]);

    expect(fn () => $this->membershipService->assertNoActiveDualMembership($athleteUser->id, $school))
        ->not->toThrow(ValidationException::class);
});

test('multiple nonclub: atlet dapat bergabung di banyak komunitas non-klub yang berbeda secara bebas', function (): void {
    $athleteUser = User::factory()->create(['is_active' => true]);

    $school = Community::factory()->create([
        'subtype' => 'school',
        'sport_category_id' => $this->basketball,
        'npsn' => '20199991',
        'status' => 'verified',
    ]);

    $university = Community::factory()->create([
        'subtype' => 'university',
        'sport_category_id' => $this->basketball,
        'status' => 'verified',
    ]);

    $institution = Community::factory()->create([
        'subtype' => 'institution',
        'sport_category_id' => $this->basketball,
        'status' => 'verified',
    ]);

    CommunityAthlete::factory()->create([
        'community_id' => $school->id,
        'user_id' => $athleteUser->id,
        'status' => 'active',
        'left_at' => null,
    ]);

    CommunityAthlete::factory()->create([
        'community_id' => $university->id,
        'user_id' => $athleteUser->id,
        'status' => 'active',
        'left_at' => null,
    ]);

    expect(fn () => $this->membershipService->assertNoActiveDualMembership($athleteUser->id, $institution))
        ->not->toThrow(ValidationException::class);
});

test('duplicate same entity rejected: pendaftaran berulang pada komunitas yang sama ditolak', function (): void {
    $athleteUser = User::factory()->create(['is_active' => true]);

    $school = Community::factory()->create([
        'subtype' => 'school',
        'sport_category_id' => $this->basketball,
        'npsn' => '20199992',
        'status' => 'verified',
    ]);

    CommunityAthlete::factory()->create([
        'community_id' => $school->id,
        'user_id' => $athleteUser->id,
        'status' => 'active',
        'left_at' => null,
    ]);

    expect(fn () => $this->membershipService->addOrInviteAthlete($school, $athleteUser->id))
        ->toThrow(ValidationException::class);
});

test('release history: atlet setelah dilepas dari klub A dapat bergabung dengan klub B', function (): void {
    $athleteUser = User::factory()->create(['is_active' => true]);

    $clubA = Community::factory()->create([
        'subtype' => 'club',
        'sport_category_id' => $this->basketball,
        'status' => 'verified',
    ]);

    $clubB = Community::factory()->create([
        'subtype' => 'club',
        'sport_category_id' => $this->basketball,
        'status' => 'verified',
    ]);

    $athleteA = CommunityAthlete::factory()->create([
        'community_id' => $clubA->id,
        'user_id' => $athleteUser->id,
        'status' => 'active',
        'left_at' => null,
    ]);

    $this->membershipService->release($athleteA);

    $athleteA->refresh();
    expect($athleteA->status)->toBe('left')
        ->and($athleteA->left_at)->not->toBeNull();

    expect(fn () => $this->membershipService->assertNoActiveDualMembership($athleteUser->id, $clubB))
        ->not->toThrow(ValidationException::class);
});

test('conservative clubs: jika target klub memiliki cabor null, cek benturan dengan klub manapun', function (): void {
    $athleteUser = User::factory()->create(['is_active' => true]);

    $clubA = Community::factory()->create([
        'subtype' => 'club',
        'sport_category_id' => $this->basketball,
        'status' => 'verified',
    ]);

    $clubGeneral = Community::factory()->create([
        'subtype' => 'club',
        'sport_category_id' => null,
        'status' => 'verified',
    ]);

    CommunityAthlete::factory()->create([
        'community_id' => $clubA->id,
        'user_id' => $athleteUser->id,
        'status' => 'active',
        'left_at' => null,
    ]);

    expect(fn () => $this->membershipService->assertNoActiveDualMembership($athleteUser->id, $clubGeneral))
        ->toThrow(ValidationException::class);
});
