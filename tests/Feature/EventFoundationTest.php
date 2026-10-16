<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\CommunityLookup;
use App\Contracts\DevelopmentLevelLookup;
use App\Contracts\DTOs\EligibleAthleteDto;
use App\Contracts\EventLookup;
use App\Contracts\OfficialDirectory;
use App\Contracts\RosterProvider;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('RosterProvider mengembalikan atlet active tanpa prasyarat region_status', function (): void {
    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $community = Community::factory()->create([
        'sport_category_id' => $sport->id,
        'status' => 'verified',
    ]);

    $otherCommunity = Community::factory()->create([
        'sport_category_id' => $sport->id,
        'status' => 'verified',
    ]);

    // Eligible athlete: active + verified
    $user1 = User::factory()->create([
        'name' => 'Atlet Lolos',
        'birth_date' => '2010-05-15',
        'gender' => 'male',
    ]);
    CommunityAthlete::factory()->create([
        'community_id' => $community->id,
        'user_id' => $user1->id,
        'status' => 'active',
        'region_status' => 'verified',
    ]);

    // Ineligible athlete: pending status
    $user2 = User::factory()->create(['name' => 'Atlet Pending Status']);
    CommunityAthlete::factory()->create([
        'community_id' => $community->id,
        'user_id' => $user2->id,
        'status' => 'pending',
        'region_status' => 'verified',
    ]);

    // Active athlete remains eligible with pending regional verification.
    $user3 = User::factory()->create(['name' => 'Atlet Pending Region']);
    CommunityAthlete::factory()->create([
        'community_id' => $community->id,
        'user_id' => $user3->id,
        'status' => 'active',
        'region_status' => 'pending',
    ]);

    // Ineligible athlete: left status, rejected region
    $user4 = User::factory()->create(['name' => 'Atlet Left Rejected']);
    CommunityAthlete::factory()->create([
        'community_id' => $community->id,
        'user_id' => $user4->id,
        'status' => 'left',
        'region_status' => 'rejected',
    ]);

    // Eligible athlete from another community
    $userOther = User::factory()->create(['name' => 'Atlet Komunitas Lain']);
    CommunityAthlete::factory()->create([
        'community_id' => $otherCommunity->id,
        'user_id' => $userOther->id,
        'status' => 'active',
        'region_status' => 'verified',
    ]);

    /** @var RosterProvider $rosterProvider */
    $rosterProvider = app(RosterProvider::class);
    $athletes = $rosterProvider->eligibleAthletes($community->id, 1);

    expect($athletes)->toHaveCount(2)
        ->and($athletes->pluck('userId')->all())->toEqualCanonicalizing([$user1->id, $user3->id])
        ->and($athletes->first())->toBeInstanceOf(EligibleAthleteDto::class)
        ->and($athletes->keyBy('userId')->get($user1->id)->name)->toBe('Atlet Lolos')
        ->and($athletes->keyBy('userId')->get($user1->id)->gender)->toBe('male');

    // DTO must not be JSON serializable (contract: cannot be serialized to response)
    expect(fn () => json_encode($athletes->first()))
        ->toThrow(LogicException::class);
});

test('DevelopmentLevelLookup mencocokkan batas umur', function (): void {
    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $levelKu12 = DevelopmentLevel::create([
        'sport_category_id' => $sport->id,
        'name' => 'KU-12',
        'min_age' => 10,
        'max_age' => 12,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $levelKu15 = DevelopmentLevel::create([
        'sport_category_id' => $sport->id,
        'name' => 'KU-15',
        'min_age' => 13,
        'max_age' => 15,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    /** @var DevelopmentLevelLookup $lookup */
    $lookup = app(DevelopmentLevelLookup::class);

    // 11 years old -> KU-12
    $birthDate11 = Carbon::now()->subYears(11);
    $summary11 = $lookup->findForBirthDate($birthDate11, $sport->id);
    expect($summary11)->not->toBeNull()
        ->and($summary11->id)->toBe($levelKu12->id)
        ->and($summary11->name)->toBe('KU-12');

    // 14 years old -> KU-15
    $birthDate14 = Carbon::now()->subYears(14);
    $summary14 = $lookup->findForBirthDate($birthDate14, $sport->id);
    expect($summary14)->not->toBeNull()
        ->and($summary14->id)->toBe($levelKu15->id)
        ->and($summary14->name)->toBe('KU-15');

    // 25 years old -> null (no matching category)
    $birthDate25 = Carbon::now()->subYears(25);
    $summary25 = $lookup->findForBirthDate($birthDate25, $sport->id);
    expect($summary25)->toBeNull();
});

test('FK RESTRICT mencegah hapus komunitas yang terdaftar sebagai peserta', function (): void {
    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $community = Community::factory()->create([
        'sport_category_id' => $sport->id,
        'status' => 'verified',
    ]);

    $event = Event::factory()->create([
        'sport_category_id' => $sport->id,
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
    ]);

    EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'registrant_type' => 'community',
        'community_id' => $community->id,
        'team_name' => 'Komunitas Elang',
        'status' => 'confirmed',
        'registered_at' => now(),
    ]);

    // Force deleting community should trigger FK RESTRICT QueryException
    expect(fn () => $community->forceDelete())
        ->toThrow(QueryException::class);
});

test('EventLookup dan CommunityLookup mengembalikan summary dengan benar', function (): void {
    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $community = Community::factory()->create([
        'sport_category_id' => $sport->id,
        'status' => 'verified',
        'city_id' => '3273',
    ]);

    $event = Event::factory()->create([
        'sport_category_id' => $sport->id,
        'title' => 'Kejuaraan Pelajar 2026',
        'status' => 'published',
    ]);

    /** @var CommunityLookup $commLookup */
    $commLookup = app(CommunityLookup::class);
    $commSummary = $commLookup->find($community->id);

    expect($commSummary)->not->toBeNull()
        ->and($commSummary->id)->toBe($community->id)
        ->and($commSummary->name)->toBe($community->name)
        ->and($commSummary->status)->toBe('verified')
        ->and($commSummary->city_id)->toBe('3273');

    /** @var EventLookup $eventLookup */
    $eventLookup = app(EventLookup::class);
    $eventSummary = $eventLookup->find($event->id);

    expect($eventSummary)->not->toBeNull()
        ->and($eventSummary->id)->toBe($event->id)
        ->and($eventSummary->title)->toBe('Kejuaraan Pelajar 2026')
        ->and($eventSummary->status)->toBe('published');

    expect($eventLookup->find(999999))->toBeNull();
    expect($commLookup->find(999999))->toBeNull();
});

test('OfficialDirectory mencari official berdasarkan peran atau izin', function (): void {
    $refereeRole = Role::firstOrCreate(['name' => 'referee', 'guard_name' => 'web']);
    $userReferee = User::factory()->create(['name' => 'Wasit Utama']);
    $userReferee->assignRole($refereeRole);

    $userNormal = User::factory()->create(['name' => 'User Biasa']);

    /** @var OfficialDirectory $directory */
    $directory = app(OfficialDirectory::class);
    $officials = $directory->search('referee');

    $ids = $officials->pluck('userId')->all();
    expect($ids)->toContain($userReferee->id)
        ->and($ids)->not->toContain($userNormal->id);
});

test('Permission Modul D terpasang pada role superadmin, admin, dan staff', function (): void {
    $superadmin = Role::findByName('superadmin', 'web');
    $admin = Role::findByName('admin', 'web');
    $staff = Role::findByName('staff', 'web');

    $eventPermissions = [
        'event.view', 'event.create', 'event.update', 'event.delete', 'event.publish',
        'event-category.view', 'event-category.create', 'event-category.update', 'event-category.delete',
        'event-participant.view', 'event-participant.create', 'event-participant.update', 'event-participant.delete', 'event-participant.confirm',
        'event-schedule.view', 'event-schedule.create', 'event-schedule.update', 'event-schedule.delete', 'event-schedule.manage',
        'event-result.view',
        'tournament.manage', 'tournament.reset', 'tournament.export', 'tournament.schedule', 'tournament.official',
    ];

    foreach ($eventPermissions as $permission) {
        expect($superadmin->hasPermissionTo($permission))->toBeTrue("Superadmin missing {$permission}");
        expect($admin->hasPermissionTo($permission))->toBeTrue("Admin missing {$permission}");
    }

    $staffPermissions = [
        'event.view',
        'event-category.view',
        'event-participant.view',
        'event-schedule.view',
        'event-result.view',
    ];

    foreach ($staffPermissions as $permission) {
        expect($staff->hasPermissionTo($permission))->toBeTrue("Staff missing {$permission}");
    }

    // Staff should not have manage/create permissions
    expect($staff->hasPermissionTo('event.create'))->toBeFalse();
    expect($staff->hasPermissionTo('tournament.manage'))->toBeFalse();
});

test('Model Event menggunakan Auditable dan mencatat log saat dibuat', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $event = Event::create([
        'sport_category_id' => $sport->id,
        'user_id' => $user->id,
        'title' => 'Turnamen Auditable',
        'slug' => 'turnamen-auditable',
        'status' => 'draft',
    ]);

    $log = ActivityLog::where('auditable_type', Event::class)
        ->where('auditable_id', $event->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('created');
});
