<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->devLevel = DevelopmentLevel::query()->firstOrCreate(
        ['name' => 'Umum', 'sport_category_id' => $this->sport->id],
        ['min_age' => 10, 'max_age' => 40, 'sort_order' => 1]
    );

    $this->admin = User::factory()->create([
        'email' => 'admin_cat_limit@test.com',
        'is_active' => true,
    ]);
    $this->admin->assignRole('superadmin');

    $this->organizer = User::factory()->create([
        'email' => 'organizer_cat_limit@test.com',
        'is_active' => true,
    ]);
    $this->organizer->assignRole('organizer');

    $this->event = Event::factory()->create([
        'user_id' => $this->organizer->id,
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => Carbon::now()->addDays(14),
        'ends_at' => Carbon::now()->addDays(20),
        'registration_deadline' => Carbon::now()->addDays(10),
        'max_participants' => 64,
    ]);

    $this->community = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
        'is_active' => true,
        'subtype' => 'club',
    ]);

    $this->guard = app(RegistrationGuard::class);
});

test('default max_teams_per_entity is 1 and allows 1 putra and 1 putri per community but rejects 2nd putra', function (): void {
    $categoryPutra = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Basket Putra U-18',
        'gender' => 'male',
        'max_teams' => 16,
        'max_teams_per_entity' => 1,
        'allow_club' => true,
        'allow_community' => false,
        'allow_individual' => false,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $categoryPutri = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Basket Putri U-18',
        'gender' => 'female',
        'max_teams' => 16,
        'max_teams_per_entity' => 1,
        'allow_club' => true,
        'allow_community' => false,
        'allow_individual' => false,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // 1. Pendaftaran tim putra pertama lolos
    $this->guard->validate(
        $this->event,
        $categoryPutra,
        $this->community->id,
        'Tim Putra Juara',
        registrationSource: 'club'
    );

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $categoryPutra->id,
        'community_id' => $this->community->id,
        'team_name' => 'Tim Putra Juara',
        'status' => 'registered',
        'registered_at' => Carbon::now(),
    ]);

    // 2. Pendaftaran tim putri pertama pada kategori berbeda juga lolos
    $this->guard->validate(
        $this->event,
        $categoryPutri,
        $this->community->id,
        'Tim Putri Juara',
        registrationSource: 'club'
    );

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $categoryPutri->id,
        'community_id' => $this->community->id,
        'team_name' => 'Tim Putri Juara',
        'status' => 'registered',
        'registered_at' => Carbon::now(),
    ]);

    // 3. Tim kedua putra pada kategori putra yang sama ditolak (default limit 1)
    expect(fn () => $this->guard->validate(
        $this->event,
        $categoryPutra,
        $this->community->id,
        'Tim Putra Garuda',
        registrationSource: 'club'
    ))->toThrow(RegistrationGuardException::class, '1 tim per kategori');
});

test('category with max_teams_per_entity 2 allows 2 putra teams and rejects 3rd', function (): void {
    $categoryPutra = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Basket Putra Divisi A',
        'gender' => 'male',
        'max_teams' => 16,
        'max_teams_per_entity' => 2,
        'allow_club' => true,
        'allow_community' => false,
        'allow_individual' => false,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // Tim 1 lolos
    $this->guard->validate(
        $this->event,
        $categoryPutra,
        $this->community->id,
        'Tim Elang A',
        registrationSource: 'club'
    );

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $categoryPutra->id,
        'community_id' => $this->community->id,
        'team_name' => 'Tim Elang A',
        'status' => 'registered',
        'registered_at' => Carbon::now(),
    ]);

    // Tim 2 dari komunitas yang sama lolos karena limit = 2
    $this->guard->validate(
        $this->event,
        $categoryPutra,
        $this->community->id,
        'Tim Elang B',
        registrationSource: 'club'
    );

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $categoryPutra->id,
        'community_id' => $this->community->id,
        'team_name' => 'Tim Elang B',
        'status' => 'confirmed',
        'registered_at' => Carbon::now(),
    ]);

    // Tim 3 ditolak karena limit 2 sudah tercapai
    expect(fn () => $this->guard->validate(
        $this->event,
        $categoryPutra,
        $this->community->id,
        'Tim Elang C',
        registrationSource: 'club'
    ))->toThrow(RegistrationGuardException::class, 'maksimal 2 tim per kategori');
});

test('updating category rejects lowering max_teams_per_entity below active count but allows raising', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Basket Multi Team',
        'gender' => 'male',
        'match_type' => '5x5',
        'max_teams' => 16,
        'max_teams_per_entity' => 2,
        'allow_club' => true,
        'allow_community' => false,
        'allow_individual' => false,
        'registration_flow' => 'screening_first',
        'registration_fee' => 0,
    ]);

    // Daftarkan 2 tim aktif dari komunitas
    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'community_id' => $this->community->id,
        'team_name' => 'Harimau 1',
        'status' => 'confirmed',
        'registered_at' => Carbon::now(),
    ]);

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'community_id' => $this->community->id,
        'team_name' => 'Harimau 2',
        'status' => 'registered',
        'registered_at' => Carbon::now(),
    ]);

    expect($category->maxActiveTeamsPerCommunity())->toBe(2);

    // 1. Coba turunkan limit menjadi 1 -> harus ditolak karena komunitas memiliki 2 tim aktif
    $responseLower = $this->actingAs($this->admin)
        ->from(route('admin.events.categories.index', $this->event))
        ->put(route('admin.events.categories.update', [$this->event, $category]), [
            'name' => 'Basket Multi Team',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'screening_first',
            'registration_fee' => 0,
            'allow_club' => 1,
            'max_teams' => 16,
            'max_teams_per_entity' => 1,
        ]);

    $responseLower->assertSessionHasErrors(['max_teams_per_entity']);
    expect($category->fresh()->max_teams_per_entity)->toBe(2);

    // 2. Naikkan limit menjadi 3 -> harus berhasil
    $responseRaise = $this->actingAs($this->admin)
        ->put(route('admin.events.categories.update', [$this->event, $category]), [
            'name' => 'Basket Multi Team',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'screening_first',
            'registration_fee' => 0,
            'allow_club' => 1,
            'max_teams' => 16,
            'max_teams_per_entity' => 3,
        ]);

    $responseRaise->assertSessionHasNoErrors();
    expect($category->fresh()->max_teams_per_entity)->toBe(3);
});

test('waiting_list and unexpired pending_payment count towards entity active quota', function (): void {
    $categoryA = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Waiting List Test',
        'gender' => 'mixed',
        'max_teams' => 8,
        'max_teams_per_entity' => 1,
        'allow_club' => true,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // Tim berstatus waiting_list tetap menghitung kuota entitas komunitas
    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $categoryA->id,
        'community_id' => $this->community->id,
        'team_name' => 'Tim Antrean',
        'status' => 'waiting_list',
        'waiting_list_order' => 1,
        'registered_at' => Carbon::now(),
    ]);

    expect(fn () => $this->guard->validate(
        $this->event,
        $categoryA,
        $this->community->id,
        'Tim Kedua Antrean',
        registrationSource: 'club'
    ))->toThrow(RegistrationGuardException::class, '1 tim per kategori');

    $categoryB = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Pending Payment Test',
        'gender' => 'mixed',
        'max_teams' => 8,
        'max_teams_per_entity' => 1,
        'allow_club' => true,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // Tim berstatus pending_payment yang belum expired tetap menghitung kuota entitas
    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $categoryB->id,
        'community_id' => $this->community->id,
        'team_name' => 'Tim Unpaid Active',
        'status' => 'pending_payment',
        'payment_deadline_at' => Carbon::now()->addHours(2),
        'registered_at' => Carbon::now(),
    ]);

    expect(fn () => $this->guard->validate(
        $this->event,
        $categoryB,
        $this->community->id,
        'Tim Kedua Unpaid',
        registrationSource: 'club'
    ))->toThrow(RegistrationGuardException::class, '1 tim per kategori');
});

test('cancelled, rejected, and expired pending_payment release the entity quota', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Release Quota Test',
        'gender' => 'mixed',
        'max_teams' => 8,
        'max_teams_per_entity' => 1,
        'allow_club' => true,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // 1. Tim status cancelled melepaskan kuota
    $pCancelled = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'community_id' => $this->community->id,
        'team_name' => 'Tim Batal',
        'status' => 'cancelled',
        'registered_at' => Carbon::now(),
    ]);

    $this->guard->validate(
        $this->event,
        $category,
        $this->community->id,
        'Tim Pengganti Batal',
        registrationSource: 'club'
    );

    // 2. Tim status rejected melepaskan kuota
    $pCancelled->update(['status' => 'rejected']);

    $this->guard->validate(
        $this->event,
        $category,
        $this->community->id,
        'Tim Pengganti Reject',
        registrationSource: 'club'
    );

    // 3. Tim pending_payment yang sudah expired melepaskan kuota
    $pCancelled->update([
        'status' => 'pending_payment',
        'payment_deadline_at' => Carbon::now()->subMinutes(30),
    ]);

    $this->guard->validate(
        $this->event,
        $category,
        $this->community->id,
        'Tim Pengganti Expired',
        registrationSource: 'club'
    );
    expect($category->maxActiveTeamsPerCommunity())->toBe(0);
});

test('team name constraint prevents duplicate names in same category even with max_teams_per_entity 2', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Basket Bebas Nama',
        'gender' => 'male',
        'max_teams' => 16,
        'max_teams_per_entity' => 2,
        'allow_club' => true,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'community_id' => $this->community->id,
        'team_name' => 'Garuda Sakti',
        'status' => 'registered',
        'registered_at' => Carbon::now(),
    ]);

    // Nama tim sama case-insensitive ditolak
    expect(fn () => $this->guard->validate(
        $this->event,
        $category,
        $this->community->id,
        'garuda sakti',
        registrationSource: 'club'
    ))->toThrow(RegistrationGuardException::class, 'sudah terdaftar');

    // Nama tim berbeda lolos
    $this->guard->validate(
        $this->event,
        $category,
        $this->community->id,
        'Garuda Perkasa',
        registrationSource: 'club'
    );
});
