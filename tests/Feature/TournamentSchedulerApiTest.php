<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\BracketNode;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Models\TournamentPhase;
use App\Modules\Event\Models\TournamentPool;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::where('email', 'superadmin@actstation.test')->first();

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->admin->id,
        'title' => 'Kejurda Basket 2026',
        'status' => 'published',
        'starts_at' => Carbon::now()->addDays(2),
        'ends_at' => Carbon::now()->addDays(5),
    ]);

    $this->category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-18 Putra',
    ]);
});

test('route GET admin/events/{event}/scheduler merender view scheduler dengan event dan apiBase', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.events.scheduler', $this->event));

    $response->assertOk()
        ->assertViewIs('admin.events.scheduler')
        ->assertViewHas('event', $this->event)
        ->assertViewHas('apiBase', url("admin/events/{$this->event->id}/scheduler/api"));
});

test('user tanpa tournament.manage mendapat 403 di endpoint tulis', function (): void {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    // POST phases
    $this->actingAs($staff)
        ->postJson(route('admin.events.scheduler.api.phases.store', $this->event), [
            'event_category_id' => $this->category->id,
            'name' => 'Babak Grup',
            'phase_type' => 'pool',
        ])
        ->assertForbidden();

    // Finalize game
    $game = Game::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'score_a' => 0,
        'score_b' => 0,
    ]);

    $this->actingAs($staff)
        ->postJson(route('admin.events.scheduler.api.finalize-game', [$this->event, $game]), [
            'score_a' => 20,
            'score_b' => 15,
        ])
        ->assertForbidden();
});

test('game event A tidak bisa diakses lewat URL event B dan menghasilkan 404', function (): void {
    $eventB = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->admin->id,
        'title' => 'Turnamen B',
        'status' => 'published',
    ]);

    $gameA = Game::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
    ]);

    // Akses via scheduler api
    $response = $this->actingAs($this->admin)
        ->putJson(route('admin.events.scheduler.api.games.update', [$eventB, $gameA]), [
            'score_a' => 10,
        ]);

    $response->assertNotFound();

    // Akses finalize via URL event B
    $finalizeResponse = $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.finalize-game', [$eventB, $gameA]), [
            'score_a' => 10,
            'score_b' => 8,
        ]);

    $finalizeResponse->assertNotFound();
});

test('finalize skor seri pada knockout menghasilkan 422 dengan shape {message, errors}', function (): void {
    $phase = TournamentPhase::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'name' => 'Knockout Stage',
        'phase_type' => 'knockout',
    ]);

    $game = Game::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'tournament_phase_id' => $phase->id,
    ]);

    BracketNode::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'tournament_phase_id' => $phase->id,
        'phase' => 'knockout',
        'round' => 1,
        'match_order' => 1,
        'node_type' => 'match',
        'game_id' => $game->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.finalize-game', [$this->event, $game]), [
            'score_a' => 50,
            'score_b' => 50,
        ]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors',
        ]);

    expect($response->json('message'))->toContain('seri');
});

test('generate dua kali tidak menduplikasi records', function (): void {
    $phase = TournamentPhase::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'name' => 'Pool Stage',
        'phase_type' => 'pool',
        'config' => [
            'pool_count' => 2,
            'round_robin_count' => 1,
        ],
    ]);

    $teams = EventParticipant::factory()->count(4)->create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
    ]);
    $teamIds = $teams->pluck('id')->all();

    // Generate pertama
    $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.phases.generate', [$this->event, $phase]), [
            'team_ids' => $teamIds,
            'config' => ['pool_count' => 2],
        ])
        ->assertOk();

    $initialPoolsCount = TournamentPool::where('tournament_phase_id', $phase->id)->count();
    $initialGamesCount = Game::where('tournament_phase_id', $phase->id)->count();

    expect($initialPoolsCount)->toBe(2)
        ->and($initialGamesCount)->toBeGreaterThan(0);

    // Generate kedua
    $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.phases.generate', [$this->event, $phase]), [
            'team_ids' => $teamIds,
            'config' => ['pool_count' => 2],
        ])
        ->assertOk();

    $secondPoolsCount = TournamentPool::where('tournament_phase_id', $phase->id)->count();
    $secondGamesCount = Game::where('tournament_phase_id', $phase->id)->count();

    expect($secondPoolsCount)->toBe($initialPoolsCount)
        ->and($secondGamesCount)->toBe($initialGamesCount);
});

test('CSV diawali BOM dan memuat header kolom', function (): void {
    $phase = TournamentPhase::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'name' => 'Pool Stage',
        'phase_type' => 'pool',
        'config' => ['pool_count' => 1],
    ]);

    $teams = EventParticipant::factory()->count(2)->create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.phases.generate', [$this->event, $phase]), [
            'team_ids' => $teams->pluck('id')->all(),
            'config' => ['pool_count' => 1],
        ])
        ->assertOk();

    $response = $this->actingAs($this->admin)
        ->get(route('admin.events.scheduler.api.export.standings-csv', [$this->event, 'phase_id' => $phase->id]));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('Pool;Rank;Team;Pld;W;D;L;PF;PA;DIFF;PTS');
});

test('JSON official tidak memuat email, phone, atau identity', function (): void {
    $referee = User::factory()->create([
        'name' => 'Budi Wasit',
        'email' => 'budi.referee@example.com',
    ]);
    $referee->givePermissionTo('tournament.official');

    $game = Game::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
    ]);

    // Plot official
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.games.officials', [$this->event, $game]), [
            'officials' => [
                ['user_id' => $referee->id, 'duty' => 'referee_1'],
            ],
        ]);

    $response->assertOk();

    $officialsData = $response->json('data.officials');
    expect($officialsData)->toBeArray()->toHaveCount(1);

    $official = $officialsData[0];
    expect($official)->toHaveKeys(['id', 'userId', 'user_id', 'name', 'duty'])
        ->and($official)->not->toHaveKey('email')
        ->and($official)->not->toHaveKey('phone')
        ->and($official)->not->toHaveKey('identity')
        ->and($official)->not->toHaveKey('nik')
        ->and($official['name'])->toBe('Budi Wasit')
        ->and($official['userId'])->toBe($referee->id);

    // List officials search endpoint
    $searchResponse = $this->actingAs($this->admin)
        ->getJson(route('admin.events.scheduler.api.officials', [$this->event, 'duty' => 'referee']));

    $searchResponse->assertOk();
    $list = $searchResponse->json('data');
    if (! empty($list)) {
        foreach ($list as $item) {
            expect($item)->not->toHaveKey('email')
                ->and($item)->not->toHaveKey('phone')
                ->and($item)->not->toHaveKey('identity');
        }
    }
});

test('config endpoint mengembalikan ringkasan turnamen lengkap', function (): void {
    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.events.scheduler.api.config', $this->event));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'slug',
                'status',
                'categories',
                'phases',
                'schedule_settings',
                'schedule_breaks',
                'courts',
                'participants',
            ],
        ]);
});

test('schedule settings, breaks, dan sync courts dapat dikelola', function (): void {
    // 1. Save schedule settings
    $settingsResponse = $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.schedule-settings.save', $this->event), [
            'start_time' => '08:00',
            'end_time_daily' => '21:00',
            'break_between_games' => 15,
            'durations' => ['pool' => 50],
        ]);

    $settingsResponse->assertSuccessful()
        ->assertJsonPath('data.break_between_games', 15);

    // 2. Store break
    $breakResponse = $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.schedule-breaks.store', $this->event), [
            'date' => '2026-10-15',
            'start_time' => '12:00',
            'end_time' => '13:00',
            'label' => 'Istirahat Siang',
        ]);

    $breakResponse->assertCreated()
        ->assertJsonPath('data.label', 'Istirahat Siang');

    $breakId = $breakResponse->json('data.id');

    // 3. Delete break
    $this->actingAs($this->admin)
        ->deleteJson(route('admin.events.scheduler.api.schedule-breaks.destroy', [$this->event, $breakId]))
        ->assertOk();

    // 4. Sync courts
    $venueId = DB::table('venues')->insertGetId([
        'name' => 'GOR Bulungan',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $court1Id = DB::table('venue_courts')->insertGetId([
        'venue_id' => $venueId,
        'name' => 'Lapangan 1',
        'is_indoor' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $syncResponse = $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.courts.sync', $this->event), [
            'venue_court_ids' => [$court1Id],
        ]);

    $syncResponse->assertOk();
    expect($this->event->eventCourts()->count())->toBe(1);
});

test('storePhase idempoten dan tidak melempar 1062 duplicate entry saat dipanggil ulang', function (): void {
    $res1 = $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.phases.store', $this->event), [
            'event_category_id' => $this->category->id,
            'name' => 'Babak Knockout',
            'phase_type' => 'knockout',
            'config' => [
                'bracket_size' => 8,
                'team_count' => 8,
                'third_place_match' => true,
                'cross_pool' => true,
                'seeding_method' => 'standings',
            ],
        ]);

    $res1->assertSuccessful();

    $res2 = $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.phases.store', $this->event), [
            'event_category_id' => $this->category->id,
            'name' => 'Babak Knockout Terupdate',
            'phase_type' => 'knockout',
            'config' => [
                'bracket_size' => 16,
                'team_count' => 16,
                'third_place_match' => false,
            ],
        ]);

    $res2->assertSuccessful();

    $phases = TournamentPhase::where('event_id', $this->event->id)
        ->where('event_category_id', $this->category->id)
        ->where('phase_type', 'knockout')
        ->get();

    expect($phases->count())->toBe(1)
        ->and($phases->first()->name)->toBe('Babak Knockout Terupdate');
});
