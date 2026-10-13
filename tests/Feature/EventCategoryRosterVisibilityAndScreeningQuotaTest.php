<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\MasterData\Models\SportCategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->sport = SportCategory::firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
    $this->organizer = User::factory()->create();
    $this->organizer->assignRole('organizer');

    $this->event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->organizer->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(12),
        'registration_deadline' => now()->addDays(8),
    ]);
});

test('screening_first membolehkan pendaftaran tim melebihi kuota max_teams tanpa masuk waiting list', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Screening Terbuka',
        'registration_flow' => 'screening_first',
        'registration_fee' => 0,
        'max_teams' => 1,
        'enable_waiting_list' => true,
        'min_roster' => 1,
        'max_roster' => 5,
        'allow_club' => false,
        'allow_community' => true,
        'community_subtype' => 'free_association',
    ]);

    // Tim 1
    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'registrant_type' => 'community',
        'team_name' => 'Klub Singa A',
        'status' => 'registered',
        'contact_person' => 'Budi',
        'contact_phone' => '08123456789',
    ]);

    // Manajer Komunitas 2 mendaftar
    $manager = User::factory()->create();
    $manager->assignRole('community_manager');
    $community = Community::create([
        'name' => 'Klub Harimau',
        'slug' => 'klub-harimau-'.uniqid(),
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
        'subtype' => 'free_association',
    ]);
    CommunityOfficial::create([
        'community_id' => $community->id,
        'user_id' => $manager->id,
        'position' => 'Manager',
        'status' => 'active',
        'started_at' => now(),
    ]);
    $player = User::factory()->create(['gender' => 'male', 'birth_date' => now()->subYears(20)->format('Y-m-d')]);
    $player->assignRole('athlete');
    CommunityAthlete::create([
        'community_id' => $community->id,
        'user_id' => $player->id,
        'jersey_number' => 10,
        'status' => 'active',
        'region_status' => 'verified',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Klub Harimau B',
            'contact_person' => 'Siti',
            'contact_phone' => '08987654321',
            'members' => [
                [
                    'user_id' => $player->id,
                    'jersey_number' => 10,
                    'is_captain' => 1,
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();

    // Tim ke-2 tetap berstatus 'registered' (bukan waiting_list) karena alurnya screening_first
    $participant = EventParticipant::where('event_category_id', $category->id)
        ->where('team_name', 'Klub Harimau B')
        ->firstOrFail();

    expect($participant->status)->toBe('registered')
        ->and($participant->waiting_list_order)->toBeNull();
});

test('organizer dapat melakukan toggle visibilitas roster publik pada kategori', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Rahasia',
        'is_roster_public' => false,
    ]);

    expect($category->is_roster_public)->toBeFalse();

    // Toggle ON
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.toggleRosterVisibility', [$this->event, $category]))
        ->assertRedirect();

    $category->refresh();
    expect($category->is_roster_public)->toBeTrue();

    // Toggle OFF
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.toggleRosterVisibility', [$this->event, $category]))
        ->assertRedirect();

    $category->refresh();
    expect($category->is_roster_public)->toBeFalse();
});

test('portal publik menyembunyikan roster tim saat is_roster_public false dan menampilkannya saat true', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Uji Privasi',
        'is_roster_public' => false,
        'registration_flow' => 'screening_first',
    ]);

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'registrant_type' => 'community',
        'team_name' => 'Tim Rahasia Perkasa',
        'status' => 'confirmed',
        'contact_person' => 'Rahasia',
        'contact_phone' => '08123456789',
    ]);

    // Saat is_roster_public = false: nama tim tidak muncul, tampil pesan dirahasiakan
    $this->get(route('events.show', $this->event->slug))
        ->assertOk()
        ->assertDontSee('Tim Rahasia Perkasa')
        ->assertSee('Daftar tim pendaftar dirahasiakan oleh panitia hingga tahap kurasi/drawing selesai.');

    // Ubah jadi publik
    $category->update(['is_roster_public' => true]);

    // Saat is_roster_public = true: nama tim muncul
    $this->get(route('events.show', $this->event->slug))
        ->assertOk()
        ->assertSee('Tim Rahasia Perkasa')
        ->assertDontSee('Daftar tim pendaftar dirahasiakan oleh panitia hingga tahap kurasi/drawing selesai.');
});
