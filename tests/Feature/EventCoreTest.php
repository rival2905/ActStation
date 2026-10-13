<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Event\Events\EventCancelled;
use App\Modules\Event\Events\EventCompleted;
use App\Modules\Event\Events\EventPublished;
use App\Modules\Event\Exceptions\InvalidLifecycleTransitionException;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\EventParticipantMember;
use App\Modules\Event\Models\EventSchedule;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\EventLifecycleService;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\Event\Services\RosterValidator;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event as EventFacade;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::where('email', 'superadmin@actstation.test')->first();

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );
});

/*
|--------------------------------------------------------------------------
| 1. Lifecycle State Machine Tests
|--------------------------------------------------------------------------
*/

test('transisi lifecycle ilegal ditolak dengan InvalidLifecycleTransitionException', function (): void {
    $lifecycle = app(EventLifecycleService::class);

    // 1. draft -> published tanpa kategori ditolak
    $eventNoCategory = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'draft',
        'starts_at' => Carbon::now()->addDays(5),
        'ends_at' => Carbon::now()->addDays(10),
    ]);
    expect(fn () => $lifecycle->publish($eventNoCategory))
        ->toThrow(InvalidLifecycleTransitionException::class, 'minimal 1 kategori');

    // 2. draft -> published tanpa tanggal ditolak
    $eventNoDate = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'draft',
        'starts_at' => null,
        'ends_at' => null,
    ]);
    EventCategory::factory()->create(['event_id' => $eventNoDate->id]);
    expect(fn () => $lifecycle->publish($eventNoDate))
        ->toThrow(InvalidLifecycleTransitionException::class, 'wajib diisi');

    // 3. draft -> published dengan starts_at >= ends_at ditolak
    $eventInvalidDates = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'draft',
        'starts_at' => Carbon::now()->addDays(10),
        'ends_at' => Carbon::now()->addDays(5),
    ]);
    EventCategory::factory()->create(['event_id' => $eventInvalidDates->id]);
    expect(fn () => $lifecycle->publish($eventInvalidDates))
        ->toThrow(InvalidLifecycleTransitionException::class, 'lebih awal');

    // 4. draft -> ongoing ditolak langsung
    expect(fn () => $lifecycle->start($eventNoCategory))
        ->toThrow(InvalidLifecycleTransitionException::class);

    // 5. draft -> completed ditolak
    expect(fn () => $lifecycle->complete($eventNoCategory))
        ->toThrow(InvalidLifecycleTransitionException::class);

    // 6. published -> ongoing sebelum starts_at ditolak
    $publishedEvent = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'starts_at' => Carbon::now()->addDays(2),
        'ends_at' => Carbon::now()->addDays(5),
    ]);
    expect(fn () => $lifecycle->start($publishedEvent))
        ->toThrow(InvalidLifecycleTransitionException::class, 'sebelum tanggal mulai');

    // 7. published -> cancelled tanpa reason ditolak
    expect(fn () => $lifecycle->cancel($publishedEvent, ''))
        ->toThrow(InvalidLifecycleTransitionException::class, 'Alasan pembatalan');

    // 8. ongoing -> completed saat ada game belum selesai ditolak
    $ongoingEvent = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'ongoing',
        'starts_at' => Carbon::now()->subDays(2),
        'ends_at' => Carbon::now()->addDays(2),
    ]);
    $cat = EventCategory::factory()->create(['event_id' => $ongoingEvent->id]);
    Game::create([
        'event_id' => $ongoingEvent->id,
        'event_category_id' => $cat->id,
        'game_status' => 'scheduled',
    ]);
    expect(fn () => $lifecycle->complete($ongoingEvent))
        ->toThrow(InvalidLifecycleTransitionException::class, 'Semua pertandingan harus berstatus');

    // 9. completed -> archived sebelum ends_at + 30 days ditolak
    $completedEvent = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'completed',
        'starts_at' => Carbon::now()->subDays(10),
        'ends_at' => Carbon::now()->subDays(5),
    ]);
    expect(fn () => $lifecycle->archive($completedEvent))
        ->toThrow(InvalidLifecycleTransitionException::class, 'minimal 30 hari');
});

test('transisi lifecycle legal berhasil dan memicu event domain', function (): void {
    EventFacade::fake([EventPublished::class, EventCancelled::class, EventCompleted::class]);
    $lifecycle = app(EventLifecycleService::class);

    // draft -> published
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'draft',
        'starts_at' => Carbon::now()->addDays(1),
        'ends_at' => Carbon::now()->addDays(3),
    ]);
    EventCategory::factory()->create(['event_id' => $event->id]);

    $lifecycle->publish($event);
    expect($event->fresh()->status)->toBe('published');
    EventFacade::assertDispatched(EventPublished::class);

    // published -> ongoing
    $event->update(['starts_at' => Carbon::now()->subHour()]);
    $lifecycle->start($event);
    expect($event->fresh()->status)->toBe('ongoing');

    // ongoing -> completed (semua game finished / bye)
    $cat = $event->categories()->first();
    Game::create([
        'event_id' => $event->id,
        'event_category_id' => $cat->id,
        'game_status' => 'finished',
    ]);
    $lifecycle->complete($event);
    expect($event->fresh()->status)->toBe('completed');
    EventFacade::assertDispatched(EventCompleted::class);

    // completed -> archived
    $event->update(['ends_at' => Carbon::now()->subDays(31)]);
    $lifecycle->archive($event);
    expect($event->fresh()->status)->toBe('archived');

    // Pembatalan (cancelled) dengan alasan
    $anotherEvent = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'starts_at' => Carbon::now()->addDays(1),
        'ends_at' => Carbon::now()->addDays(3),
    ]);
    $lifecycle->cancel($anotherEvent, 'Bencana alam');
    expect($anotherEvent->fresh()->status)->toBe('cancelled')
        ->and($anotherEvent->fresh()->cancel_reason)->toBe('Bencana alam');
    EventFacade::assertDispatched(EventCancelled::class);
});

/*
|--------------------------------------------------------------------------
| 2. RegistrationGuard Tests
|--------------------------------------------------------------------------
*/

test('RegistrationGuard menolak pendaftaran tidak memenuhi kriteria', function (): void {
    /** @var RegistrationGuard $guard */
    $guard = app(RegistrationGuard::class);

    $verifiedCommunity = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);
    $pendingCommunity = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'pending',
    ]);

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'registration_deadline' => Carbon::now()->addDays(3),
        'max_participants' => 10,
    ]);
    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
        'max_teams' => 2,
        'allow_club' => true,
        'allow_community' => false,
    ]);

    // 1. Komunitas non-verified ditolak
    expect(fn () => $guard->validate($event, $category, $pendingCommunity->id, 'Tim Pending'))
        ->toThrow(RegistrationGuardException::class, 'verified');

    // 2. Event non-published ditolak
    $draftEvent = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'draft',
        'registration_open' => true,
    ]);
    expect(fn () => $guard->validate($draftEvent, $category, $verifiedCommunity->id, 'Tim Draft'))
        ->toThrow(RegistrationGuardException::class, 'published');

    // 3. Registrasi ditutup ditolak
    $closedEvent = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => false,
    ]);
    expect(fn () => $guard->validate($closedEvent, $category, $verifiedCommunity->id, 'Tim Closed'))
        ->toThrow(RegistrationGuardException::class, 'ditutup');

    // 4. Setelah deadline ditolak
    $expiredEvent = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'registration_deadline' => Carbon::now()->subDay(),
    ]);
    expect(fn () => $guard->validate($expiredEvent, $category, $verifiedCommunity->id, 'Tim Expired'))
        ->toThrow(RegistrationGuardException::class, 'berakhir');

    // 5. Pendaftaran pertama lolos
    $guard->validate($event, $category, $verifiedCommunity->id, 'Tim Juara');
    EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'community_id' => $verifiedCommunity->id,
        'team_name' => 'Tim Juara',
        'status' => 'confirmed',
    ]);

    // 6. Komunitas ganda pada kategori yang sama ditolak
    expect(fn () => $guard->validate($event, $category, $verifiedCommunity->id, 'Tim Juara Dua'))
        ->toThrow(RegistrationGuardException::class, '1 tim per kategori');

    // 7. Nama tim duplikat case-insensitive ditolak
    $otherCommunity = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);
    expect(fn () => $guard->validate($event, $category, $otherCommunity->id, 'tim juara'))
        ->toThrow(RegistrationGuardException::class, 'sudah terdaftar');

    // 8. Kuota max_teams kategori penuh ditolak (setelah 2 tim terdaftar)
    EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'community_id' => $otherCommunity->id,
        'team_name' => 'Tim Garuda',
        'status' => 'confirmed',
    ]);

    $thirdCommunity = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);
    expect(fn () => $guard->validate($event, $category, $thirdCommunity->id, 'Tim Elang'))
        ->toThrow(RegistrationGuardException::class, 'kategori ini sudah penuh');

    $category->update(['registration_flow' => 'screening_first']);
    $guard->validate($event, $category->fresh(), $thirdCommunity->id, 'Tim Elang');
});

test('RegistrationGuard menolak jika kuota max_participants event penuh', function (): void {
    /** @var RegistrationGuard $guard */
    $guard = app(RegistrationGuard::class);

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'max_participants' => 1,
    ]);
    $cat1 = EventCategory::factory()->create(['event_id' => $event->id, 'max_teams' => 10, 'allow_club' => true, 'allow_community' => false]);
    $cat2 = EventCategory::factory()->create(['event_id' => $event->id, 'max_teams' => 10, 'allow_club' => true, 'allow_community' => false]);

    $comm1 = Community::factory()->create(['sport_category_id' => $this->sport->id, 'status' => 'verified']);
    $comm2 = Community::factory()->create(['sport_category_id' => $this->sport->id, 'status' => 'verified']);

    EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $cat1->id,
        'community_id' => $comm1->id,
        'team_name' => 'Tim A',
        'status' => 'registered',
    ]);

    expect(fn () => $guard->validate($event, $cat2, $comm2->id, 'Tim B'))
        ->toThrow(RegistrationGuardException::class, 'peserta event sudah penuh');
});

/*
|--------------------------------------------------------------------------
| 3. RosterValidator Tests
|--------------------------------------------------------------------------
*/

test('RosterValidator menolak ketidaksesuaian roster: umur, gender, jersey, kapten, lintas-tim, eligibility, min-max', function (): void {
    /** @var RosterValidator $validator */
    $validator = app(RosterValidator::class);

    $levelKu12 = DevelopmentLevel::create([
        'sport_category_id' => $this->sport->id,
        'name' => 'KU-12',
        'min_age' => 10,
        'max_age' => 12,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $community = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'starts_at' => Carbon::now()->addDays(5),
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
        'development_level_id' => $levelKu12->id,
        'gender' => 'male',
        'min_roster' => 5,
        'max_roster' => 7,
    ]);

    $participant = EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'community_id' => $community->id,
        'team_name' => 'Elang Sakti',
        'status' => 'confirmed',
    ]);

    // Atlet 1: 11 tahun, male, eligible
    $u1 = User::factory()->create(['birth_date' => Carbon::now()->subYears(11), 'gender' => 'male']);
    CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $u1->id, 'status' => 'active', 'region_status' => 'verified']);

    // Atlet 2: 11 tahun, male, eligible
    $u2 = User::factory()->create(['birth_date' => Carbon::now()->subYears(11), 'gender' => 'male']);
    CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $u2->id, 'status' => 'active', 'region_status' => 'verified']);

    // Atlet 3: 11 tahun, male, eligible
    $u3 = User::factory()->create(['birth_date' => Carbon::now()->subYears(11), 'gender' => 'male']);
    CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $u3->id, 'status' => 'active', 'region_status' => 'verified']);

    // Atlet 4: 11 tahun, male, eligible
    $u4 = User::factory()->create(['birth_date' => Carbon::now()->subYears(11), 'gender' => 'male']);
    CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $u4->id, 'status' => 'active', 'region_status' => 'verified']);

    // Atlet 5: 11 tahun, male, eligible
    $u5 = User::factory()->create(['birth_date' => Carbon::now()->subYears(11), 'gender' => 'male']);
    CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $u5->id, 'status' => 'active', 'region_status' => 'verified']);

    // A. Uji batas min-max roster: 4 atlet (min-1) ditolak
    $roster4 = [
        ['user_id' => $u1->id, 'jersey_number' => 1, 'is_captain' => true],
        ['user_id' => $u2->id, 'jersey_number' => 2, 'is_captain' => false],
        ['user_id' => $u3->id, 'jersey_number' => 3, 'is_captain' => false],
        ['user_id' => $u4->id, 'jersey_number' => 4, 'is_captain' => false],
    ];
    try {
        $validator->validate($participant, $roster4);
        $this->fail('Harusnya gagal karena min roster');
    } catch (RosterValidationException $e) {
        expect($e->errors)->toHaveKey('roster');
    }

    // B. Uji tepat min (5 atlet) lolos
    $roster5 = [
        ['user_id' => $u1->id, 'jersey_number' => 1, 'is_captain' => true],
        ['user_id' => $u2->id, 'jersey_number' => 2, 'is_captain' => false],
        ['user_id' => $u3->id, 'jersey_number' => 3, 'is_captain' => false],
        ['user_id' => $u4->id, 'jersey_number' => 4, 'is_captain' => false],
        ['user_id' => $u5->id, 'jersey_number' => 5, 'is_captain' => false],
    ];
    $validator->validate($participant, $roster5); // lolos tanpa exception

    // C. Uji kapten: tanpa kapten ditolak
    $rosterNoCaptain = $roster5;
    $rosterNoCaptain[0]['is_captain'] = false;
    try {
        $validator->validate($participant, $rosterNoCaptain);
        $this->fail('Harusnya gagal karena tanpa kapten');
    } catch (RosterValidationException $e) {
        expect($e->errors)->toHaveKey('captain');
    }

    // D. Uji kapten: 2 kapten ditolak
    $rosterTwoCaptains = $roster5;
    $rosterTwoCaptains[1]['is_captain'] = true;
    try {
        $validator->validate($participant, $rosterTwoCaptains);
        $this->fail('Harusnya gagal karena dua kapten');
    } catch (RosterValidationException $e) {
        expect($e->errors)->toHaveKey('captain');
    }

    // E. Uji jersey dobel ditolak
    $rosterDuplicateJersey = $roster5;
    $rosterDuplicateJersey[1]['jersey_number'] = 1; // sama dengan u1
    try {
        $validator->validate($participant, $rosterDuplicateJersey);
        $this->fail('Harusnya gagal karena nomor punggung duplikat');
    } catch (RosterValidationException $e) {
        expect($e->errors)->toHaveKey('members.1.jersey_number');
    }

    // F. Uji atlet tidak eligible (pending di komunitas)
    $uNotEligible = User::factory()->create(['birth_date' => Carbon::now()->subYears(11), 'gender' => 'male']);
    CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $uNotEligible->id, 'status' => 'pending', 'region_status' => 'verified']);

    $rosterIneligible = $roster5;
    $rosterIneligible[4]['user_id'] = $uNotEligible->id;
    try {
        $validator->validate($participant, $rosterIneligible);
        $this->fail('Harusnya gagal karena atlet tidak eligible');
    } catch (RosterValidationException $e) {
        expect($e->errors)->toHaveKey('members.4.user_id');
    }

    // G. Uji umur di luar jenjang (17 tahun untuk KU-12)
    $uOverage = User::factory()->create(['birth_date' => Carbon::now()->subYears(17), 'gender' => 'male']);
    CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $uOverage->id, 'status' => 'active', 'region_status' => 'verified']);

    $rosterOverage = $roster5;
    $rosterOverage[4]['user_id'] = $uOverage->id;
    try {
        $validator->validate($participant, $rosterOverage);
        $this->fail('Harusnya gagal karena umur di luar jenjang');
    } catch (RosterValidationException $e) {
        expect($e->errors)->toHaveKey('members.4.age');
    }

    // H. Uji gender salah (female di kategori male)
    $uFemale = User::factory()->create(['birth_date' => Carbon::now()->subYears(11), 'gender' => 'female']);
    CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $uFemale->id, 'status' => 'active', 'region_status' => 'verified']);

    $rosterFemale = $roster5;
    $rosterFemale[4]['user_id'] = $uFemale->id;
    try {
        $validator->validate($participant, $rosterFemale);
        $this->fail('Harusnya gagal karena gender tidak sesuai');
    } catch (RosterValidationException $e) {
        expect($e->errors)->toHaveKey('members.4.gender');
    }

    // I. Uji atlet lintas-tim pada kategori yang sama
    $commOther = Community::factory()->create(['sport_category_id' => $this->sport->id, 'status' => 'verified']);
    $partOther = EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'community_id' => $commOther->id,
        'team_name' => 'Macan Tutul',
        'status' => 'confirmed',
    ]);
    EventParticipantMember::create([
        'event_participant_id' => $partOther->id,
        'user_id' => $u1->id,
        'jersey_number' => 77,
    ]);

    try {
        $validator->validate($participant, $roster5); // u1 ada di partOther
        $this->fail('Harusnya gagal karena atlet sudah di tim lain pada kategori sama');
    } catch (RosterValidationException $e) {
        expect($e->errors)->toHaveKey('members.0.user_id');
    }

    // J. Uji kunci roster saat ada game finished di kategori
    Game::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'game_status' => 'finished',
    ]);
    expect(fn () => $validator->validate($participant, $roster5))
        ->toThrow(RosterValidationException::class, 'Roster terkunci');
});

/*
|--------------------------------------------------------------------------
| 4. Controller HTTP / Blade / FullCalendar Integration Tests
|--------------------------------------------------------------------------
*/

test('Admin dapat mengakses halaman event, kategori, peserta, dan kalender feed', function (): void {
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'title' => 'Kejurda Basket U-12',
        'status' => 'published',
        'registration_open' => true,
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Putra KU-12',
        'gender' => 'male',
        'min_roster' => 5,
        'max_roster' => 12,
    ]);

    $community = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);

    $participant = EventParticipant::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'community_id' => $community->id,
        'team_name' => 'Satria Muda',
        'status' => 'registered',
    ]);

    // 1. Index events
    $this->actingAs($this->admin)
        ->get(route('admin.events.index'))
        ->assertOk();

    // 2. Create event form
    $this->actingAs($this->admin)
        ->get(route('admin.events.create'))
        ->assertOk();

    // 3. Show event
    $this->actingAs($this->admin)
        ->get(route('admin.events.show', $event))
        ->assertOk();

    // 4. Edit event
    $this->actingAs($this->admin)
        ->get(route('admin.events.edit', $event))
        ->assertOk();

    // 5. Categories index
    $this->actingAs($this->admin)
        ->get(route('admin.events.categories.index', $event))
        ->assertOk();

    // 6. Participants index
    $this->actingAs($this->admin)
        ->get(route('admin.events.participants.index', $event))
        ->assertOk();

    // 7. Participant roster form
    $this->actingAs($this->admin)
        ->get(route('admin.events.participants.roster', [$event, $participant]))
        ->assertOk();

    // 8. Schedules index
    $this->actingAs($this->admin)
        ->get(route('admin.events.schedules.index', $event))
        ->assertOk();

    // 9. Calendar view
    $this->actingAs($this->admin)
        ->get(route('admin.events.calendar'))
        ->assertOk();

    // 10. Calendar Feed JSON menggabungkan games dan schedules
    EventSchedule::create([
        'event_id' => $event->id,
        'title' => 'Technical Meeting',
        'scheduled_at' => Carbon::now()->addDays(1)->setTime(10, 0),
        'duration_minutes' => 90,
    ]);

    Game::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'match_label' => 'Game 1',
        'team_a_label' => 'Tim Alpha',
        'team_b_label' => 'Tim Beta',
        'game_time' => Carbon::now()->addDays(2)->setTime(14, 0),
        'game_status' => 'scheduled',
    ]);

    $feedResponse = $this->actingAs($this->admin)
        ->getJson(route('admin.events.calendar.feed'));

    $feedResponse->assertOk()
        ->assertJsonCount(2);

    $feedItems = $feedResponse->json();
    expect($feedItems[0]['extendedProps']['type'])->toBe('game')
        ->and($feedItems[1]['extendedProps']['type'])->toBe('schedule')
        ->and($feedItems[1]['title'])->toBe('Technical Meeting');
});

test('HTTP actions: transisi status, pendaftaran peserta, dan update roster bekerja end-to-end', function (): void {
    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'title' => 'Kejuaraan Basket Antar Klub',
        'status' => 'draft',
        'registration_open' => true,
        'starts_at' => Carbon::now()->addDays(5),
        'ends_at' => Carbon::now()->addDays(10),
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'KU-12 Putra',
        'gender' => 'male',
        'min_roster' => 5,
        'max_roster' => 7,
    ]);

    $verifiedCommunity = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);

    $unverifiedCommunity = Community::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'pending',
    ]);

    // 1. Transisi draft -> published via HTTP
    $this->actingAs($this->admin)
        ->from(route('admin.events.show', $event))
        ->post(route('admin.events.status', $event), [
            'status' => 'published',
        ])
        ->assertRedirect(route('admin.events.show', $event))
        ->assertSessionHas('success');

    expect($event->fresh()->status)->toBe('published');

    // 2. Transisi ilegal via HTTP: published -> completed langsung melempar error flash
    $this->actingAs($this->admin)
        ->from(route('admin.events.show', $event))
        ->post(route('admin.events.status', $event), [
            'status' => 'completed',
        ])
        ->assertRedirect(route('admin.events.show', $event))
        ->assertSessionHas('error');

    // 3. Pendaftaran komunitas non-verified via HTTP ditolak
    $this->actingAs($this->admin)
        ->from(route('admin.events.participants.index', $event))
        ->post(route('admin.events.participants.store', $event), [
            'event_category_id' => $category->id,
            'community_id' => $unverifiedCommunity->id,
            'team_name' => 'Klub Pending',
        ])
        ->assertRedirect(route('admin.events.participants.index', $event))
        ->assertSessionHas('error');

    // 4. Pendaftaran komunitas verified via HTTP berhasil
    $this->actingAs($this->admin)
        ->from(route('admin.events.participants.index', $event))
        ->post(route('admin.events.participants.store', $event), [
            'event_category_id' => $category->id,
            'community_id' => $verifiedCommunity->id,
            'team_name' => 'Klub Juara',
        ])
        ->assertRedirect(route('admin.events.participants.index', $event))
        ->assertSessionHas('success');

    $participant = EventParticipant::where('team_name', 'Klub Juara')->firstOrFail();

    // 5. Buat 5 atlet verified untuk Klub Juara
    $athletes = [];
    for ($i = 1; $i <= 5; $i++) {
        $user = User::factory()->create([
            'birth_date' => Carbon::now()->subYears(11),
            'gender' => 'male',
        ]);
        CommunityAthlete::factory()->create([
            'community_id' => $verifiedCommunity->id,
            'user_id' => $user->id,
            'status' => 'active',
            'region_status' => 'verified',
        ]);
        $athletes[] = $user;
    }

    // 6. Update roster tidak valid (tanpa kapten) via HTTP ditolak dengan session errors
    $invalidRosterData = array_map(function ($athlete, $index) {
        return [
            'user_id' => $athlete->id,
            'jersey_number' => $index + 10,
            'is_captain' => false,
        ];
    }, $athletes, array_keys($athletes));

    $this->actingAs($this->admin)
        ->from(route('admin.events.participants.roster', [$event, $participant]))
        ->put(route('admin.events.participants.roster.update', [$event, $participant]), [
            'members' => $invalidRosterData,
        ])
        ->assertRedirect(route('admin.events.participants.roster', [$event, $participant]))
        ->assertSessionHas('error')
        ->assertSessionHasErrors(['captain']);

    // 7. Update roster valid (tepat 1 kapten, 5 atlet) via HTTP berhasil
    $validRosterData = $invalidRosterData;
    $validRosterData[0]['is_captain'] = true;

    $this->actingAs($this->admin)
        ->from(route('admin.events.participants.roster', [$event, $participant]))
        ->put(route('admin.events.participants.roster.update', [$event, $participant]), [
            'members' => $validRosterData,
        ])
        ->assertRedirect(route('admin.events.participants.roster', [$event, $participant]))
        ->assertSessionHas('success');

    expect($participant->members()->count())->toBe(5)
        ->and($participant->members()->where('is_captain', true)->count())->toBe(1);
});

test('IDOR regression: DELETE kategori event B lewat URL event A ditolak 404 dan kategori tetap ada', function (): void {
    $eventA = Event::factory()->create([
        'user_id' => $this->admin->id,
        'sport_category_id' => $this->sport->id,
    ]);
    $eventB = Event::factory()->create([
        'user_id' => $this->admin->id,
        'sport_category_id' => $this->sport->id,
    ]);
    $categoryB = EventCategory::factory()->create([
        'event_id' => $eventB->id,
        'name' => 'Kategori Event B',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.events.categories.destroy', [$eventA, $categoryB]))
        ->assertNotFound();

    expect(EventCategory::find($categoryB->id))->not->toBeNull();
});
