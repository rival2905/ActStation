<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\BracketNode;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventCourt;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\EventScheduleBreak;
use App\Modules\Event\Models\EventScheduleSetting;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Models\PoolTeam;
use App\Modules\Event\Models\TournamentPhase;
use App\Modules\Event\Models\TournamentPool;
use App\Modules\Event\Services\Tournament\BracketGenerator;
use App\Modules\Event\Services\Tournament\BracketSeeder;
use App\Modules\Event\Services\Tournament\GameResultFinalizer;
use App\Modules\Event\Services\Tournament\PlacementGenerator;
use App\Modules\Event\Services\Tournament\PoolGenerator;
use App\Modules\Event\Services\Tournament\ScheduleCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

uses(DatabaseTransactions::class);

test('PoolGenerator menghasilkan n(n-1)/2 game per putaran dan tiap pasangan bertemu tepat sekali', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $phase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Pool Stage',
        'phase_type' => 'pool',
        'phase_order' => 1,
        'status' => 'draft',
        'config' => [
            'pool_count' => 1,
            'round_robin_count' => 1,
            'point_win' => 2,
            'point_draw' => 1,
            'point_lose' => 0,
        ],
    ]);

    $teams = EventParticipant::factory()->count(4)->create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
    ]);
    $teamIds = $teams->pluck('id')->all();

    $generator = new PoolGenerator;
    $resultPhase = $generator->generate($phase, $teamIds);

    // 4 teams -> 4*3/2 = 6 games
    expect($resultPhase->games)->toHaveCount(6);

    $pairs = [];
    foreach ($resultPhase->games as $game) {
        expect($game->team_a_id)->not->toBe($game->team_b_id);
        $pair = [$game->team_a_id, $game->team_b_id];
        sort($pair);
        $pairs[] = implode('-', $pair);
    }

    // Every pair meets exactly once
    expect(array_unique($pairs))->toHaveCount(6);

    // 2 round robins (home & away) -> 12 games
    $resultPhase2 = $generator->generate($phase, $teamIds, ['round_robin_count' => 2]);
    expect($resultPhase2->games)->toHaveCount(12);

    // Idempotent test: calling again doesn't duplicate
    expect(TournamentPool::query()->where('tournament_phase_id', $phase->id)->count())->toBe(1);
    expect(PoolTeam::query()->count())->toBe(4);
});

test('BracketGenerator membuat node = ukuran bracket - 1 dan menangani third place match', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $phase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Knockout Stage',
        'phase_type' => 'knockout',
        'phase_order' => 2,
        'status' => 'draft',
        'config' => [
            'bracket_size' => 8,
            'third_place_match' => true,
        ],
    ]);

    $generator = new BracketGenerator;
    $resultPhase = $generator->generate($phase);

    // Bracket size 8 -> 8 - 1 = 7 knockout nodes + 1 third place match = 8 nodes
    $knockoutNodes = BracketNode::query()->where('tournament_phase_id', $phase->id)->where('phase', 'knockout')->get();
    expect($knockoutNodes)->toHaveCount(7);

    $thirdPlaceNode = BracketNode::query()->where('tournament_phase_id', $phase->id)->where('phase', 'third_place')->first();
    expect($thirdPlaceNode)->not->toBeNull();

    // Final node has next_node_id null
    $finalNode = BracketNode::query()
        ->where('tournament_phase_id', $phase->id)
        ->where('phase', 'knockout')
        ->where('round', 3)
        ->first();
    expect($finalNode->next_node_id)->toBeNull();

    // Semifinals route losers to third place match
    $sfNodes = BracketNode::query()
        ->where('tournament_phase_id', $phase->id)
        ->where('phase', 'knockout')
        ->where('round', 2)
        ->get();
    expect($sfNodes)->toHaveCount(2);
    foreach ($sfNodes as $sf) {
        expect($sf->next_node_id)->toBe($finalNode->id);
        expect($sf->loser_next_node_id)->toBe($thirdPlaceNode->id);
    }
});

test('BracketSeeder memberi bye yang benar jika jumlah tim bukan pangkat 2 dan node bracket tetap ukuran bracket - 1', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $phase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Knockout Stage',
        'phase_type' => 'knockout',
        'phase_order' => 2,
        'status' => 'draft',
        'config' => ['bracket_size' => 8, 'third_place_match' => false],
    ]);

    $generator = new BracketGenerator;
    $generator->generate($phase);

    // 6 teams seeded into bracket size 8 (2 byes)
    $teams = EventParticipant::factory()->count(6)->create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
    ]);
    $teamIds = $teams->pluck('id')->all();

    $seeder = new BracketSeeder;
    $seeder->seed($phase, $teamIds, crossPool: false);

    // Node count remains 8 - 1 = 7
    expect(BracketNode::query()->where('tournament_phase_id', $phase->id)->count())->toBe(7);

    // Check round 1 matches
    $r1Games = Game::query()
        ->where('tournament_phase_id', $phase->id)
        ->where('round_label', 'Quarterfinals')
        ->get();

    $byeGames = $r1Games->where('is_bye', true);
    expect($byeGames)->toHaveCount(2);

    // High seeds (Team 1 and Team 2) get Byes and advance automatically to Round 2
    $r2Games = Game::query()
        ->where('tournament_phase_id', $phase->id)
        ->where('round_label', 'Semifinals')
        ->get();

    $r2TeamIds = $r2Games->pluck('team_a_id')->merge($r2Games->pluck('team_b_id'))->filter()->all();
    expect($r2TeamIds)->toContain($teamIds[0]); // Seed 1 advanced
    expect($r2TeamIds)->toContain($teamIds[1]); // Seed 2 advanced
});

test('BracketSeeder cross-pool tidak mempertemukan tim satu pool di ronde pertama', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $knockoutPhase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Knockout Stage',
        'phase_type' => 'knockout',
        'phase_order' => 2,
        'status' => 'draft',
        'config' => ['bracket_size' => 4, 'third_place_match' => false],
    ]);

    $generator = new BracketGenerator;
    $generator->generate($knockoutPhase);

    $poolPhase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Pool Stage',
        'phase_type' => 'pool',
        'phase_order' => 1,
        'status' => 'draft',
    ]);

    $poolA = TournamentPool::create(['tournament_phase_id' => $poolPhase->id, 'name' => 'Pool A', 'pool_order' => 1]);
    $poolB = TournamentPool::create(['tournament_phase_id' => $poolPhase->id, 'name' => 'Pool B', 'pool_order' => 2]);

    $teams = EventParticipant::factory()->count(4)->create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
    ]);

    $pt1A = PoolTeam::create(['tournament_pool_id' => $poolA->id, 'event_participant_id' => $teams[0]->id, 'rank' => 1, 'seed' => 1]);
    $pt2A = PoolTeam::create(['tournament_pool_id' => $poolA->id, 'event_participant_id' => $teams[1]->id, 'rank' => 2, 'seed' => 2]);
    $pt1B = PoolTeam::create(['tournament_pool_id' => $poolB->id, 'event_participant_id' => $teams[2]->id, 'rank' => 1, 'seed' => 1]);
    $pt2B = PoolTeam::create(['tournament_pool_id' => $poolB->id, 'event_participant_id' => $teams[3]->id, 'rank' => 2, 'seed' => 2]);

    $seeder = new BracketSeeder;
    $seeder->seed($knockoutPhase, [$pt1A, $pt2A, $pt1B, $pt2B], crossPool: true);

    $r1Games = Game::query()
        ->where('tournament_phase_id', $knockoutPhase->id)
        ->where('round_label', 'Semifinals')
        ->get();

    // Match 1: 1A vs 2B
    $game1 = $r1Games[0];
    expect($game1->team_a_id)->toBe($teams[0]->id);
    expect($game1->team_b_id)->toBe($teams[3]->id);

    // Match 2: 1B vs 2A
    $game2 = $r1Games[1];
    expect($game2->team_a_id)->toBe($teams[2]->id);
    expect($game2->team_b_id)->toBe($teams[1]->id);

    // No teams from the same pool meet in Round 1
    $poolOfTeam = [
        $teams[0]->id => 'A',
        $teams[1]->id => 'A',
        $teams[2]->id => 'B',
        $teams[3]->id => 'B',
    ];
    foreach ($r1Games as $g) {
        expect($poolOfTeam[$g->team_a_id])->not->toBe($poolOfTeam[$g->team_b_id]);
    }
});

test('PlacementGenerator membuat bracket posisi 5-8 dan menghubungkan loser QF', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $knockoutPhase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Knockout Stage',
        'phase_type' => 'knockout',
        'phase_order' => 2,
        'status' => 'draft',
        'config' => ['bracket_size' => 8],
    ]);

    $bracketGen = new BracketGenerator;
    $bracketGen->generate($knockoutPhase);

    $placementPhase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Placement Stage',
        'phase_type' => 'placement',
        'phase_order' => 3,
        'status' => 'draft',
    ]);

    $placementGen = new PlacementGenerator;
    $placementGen->generate($placementPhase, positions: [5, 7], knockoutPhase: $knockoutPhase);

    // QF losers connected to placement semis
    $qfNodes = BracketNode::query()
        ->where('tournament_phase_id', $knockoutPhase->id)
        ->where('round', 1)
        ->orderBy('match_order')
        ->get();

    expect($qfNodes[0]->loser_next_node_id)->not->toBeNull();
    expect($qfNodes[1]->loser_next_node_id)->toBe($qfNodes[0]->loser_next_node_id);
    expect($qfNodes[0]->loser_next_slot)->toBe('a');
    expect($qfNodes[1]->loser_next_slot)->toBe('b');

    // Placement nodes created (2 semis + 5th place + 7th place = 4 nodes)
    $placementNodes = BracketNode::query()->where('tournament_phase_id', $placementPhase->id)->get();
    expect($placementNodes)->toHaveCount(4);
});

test('GameResultFinalizer memajukan pemenang ke node berikutnya dan menolak skor seri pada knockout', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $phase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Knockout Stage',
        'phase_type' => 'knockout',
        'phase_order' => 2,
        'status' => 'draft',
        'config' => ['bracket_size' => 4, 'third_place_match' => true],
    ]);

    $gen = new BracketGenerator;
    $gen->generate($phase);

    $teams = EventParticipant::factory()->count(4)->create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
    ]);

    $seeder = new BracketSeeder;
    $seeder->seed($phase, $teams->pluck('id')->all(), crossPool: false);

    $sf1Node = BracketNode::query()
        ->where('tournament_phase_id', $phase->id)
        ->where('phase', 'knockout')
        ->where('round', 1)
        ->where('match_order', 1)
        ->first();

    $finalizer = new GameResultFinalizer;

    // Tie score rejected in knockout
    expect(fn () => $finalizer->finalize($sf1Node->game_id, 70, 70))
        ->toThrow(InvalidArgumentException::class, 'Skor seri tidak diperbolehkan pada babak knockout.');

    // Finalize with winner
    $finalizer->finalize($sf1Node->game_id, 80, 65);

    $finalNode = BracketNode::query()
        ->where('tournament_phase_id', $phase->id)
        ->where('phase', 'knockout')
        ->where('round', 2)
        ->first();
    $thirdPlaceNode = BracketNode::query()
        ->where('tournament_phase_id', $phase->id)
        ->where('phase', 'third_place')
        ->first();

    // Winner team advanced to Final slot A
    expect($finalNode->game->fresh()->team_a_id)->toBe($sf1Node->game->team_a_id);
    // Loser team routed to 3rd place slot A
    expect($thirdPlaceNode->game->fresh()->team_a_id)->toBe($sf1Node->game->team_b_id);

    // Guard: reject score edit if descendant node game is already finished
    $finalGame = $finalNode->game;
    $finalGame->update(['score_a' => 90, 'score_b' => 85, 'game_status' => 'finished']);

    expect(fn () => $finalizer->finalize($sf1Node->game_id, 82, 65))
        ->toThrow(LogicException::class, 'Edit skor ditolak: game di ronde berikutnya sudah selesai.');

    // Reset finalGame to scheduled, but mark thirdPlaceGame as finished
    $finalGame->update(['game_status' => 'scheduled']);
    $thirdPlaceGame = $thirdPlaceNode->game;
    $thirdPlaceGame->update(['score_a' => 50, 'score_b' => 45, 'game_status' => 'finished']);

    expect(fn () => $finalizer->finalize($sf1Node->game_id, 82, 65))
        ->toThrow(LogicException::class, 'Edit skor ditolak: game perebutan posisi berikutnya sudah selesai.');
});

test('GameResultFinalizer menghitung standings dan tie-breaker sesuai config', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $phase = TournamentPhase::create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
        'name' => 'Pool Stage',
        'phase_type' => 'pool',
        'phase_order' => 1,
        'status' => 'draft',
        'config' => [
            'pool_count' => 1,
            'point_win' => 2,
            'point_draw' => 1,
            'point_lose' => 0,
        ],
    ]);

    $teams = EventParticipant::factory()->count(3)->create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
    ]);
    $t1 = $teams[0]->id;
    $t2 = $teams[1]->id;
    $t3 = $teams[2]->id;

    $gen = new PoolGenerator;
    $gen->generate($phase, [$t1, $t2, $t3]);

    $finalizer = new GameResultFinalizer;

    // G1: T1 vs T2 -> T1 wins (80 - 70)
    $g1 = Game::where('tournament_phase_id', $phase->id)
        ->where(fn ($q) => $q->where('team_a_id', $t1)->where('team_b_id', $t2)
            ->orWhere('team_a_id', $t2)->where('team_b_id', $t1))
        ->first();
    $finalizer->finalize($g1->id, $g1->team_a_id === $t1 ? 80 : 70, $g1->team_a_id === $t1 ? 70 : 80);

    // G2: T2 vs T3 -> T2 wins (75 - 60)
    $g2 = Game::where('tournament_phase_id', $phase->id)
        ->where(fn ($q) => $q->where('team_a_id', $t2)->where('team_b_id', $t3)
            ->orWhere('team_a_id', $t3)->where('team_b_id', $t2))
        ->first();
    $finalizer->finalize($g2->id, $g2->team_a_id === $t2 ? 75 : 60, $g2->team_a_id === $t2 ? 60 : 75);

    // G3: T3 vs T1 -> T3 wins (70 - 60)
    $g3 = Game::where('tournament_phase_id', $phase->id)
        ->where(fn ($q) => $q->where('team_a_id', $t3)->where('team_b_id', $t1)
            ->orWhere('team_a_id', $t1)->where('team_b_id', $t3))
        ->first();
    $finalizer->finalize($g3->id, $g3->team_a_id === $t3 ? 70 : 60, $g3->team_a_id === $t3 ? 60 : 70);

    // All 3 teams have 1 win and 1 loss (2 pool points each).
    // Tiebreaker applies:
    // H2H points: equal (2 pts each)
    // H2H differential:
    // T1: (80-70) + (60-70) = +10 - 10 = 0
    // T2: (70-80) + (75-60) = -10 + 15 = +5
    // T3: (60-75) + (70-60) = -15 + 10 = -5
    // Therefore T2 (+5) ranks 1, T1 (0) ranks 2, T3 (-5) ranks 3!
    $pt1 = PoolTeam::where('event_participant_id', $t1)->first();
    $pt2 = PoolTeam::where('event_participant_id', $t2)->first();
    $pt3 = PoolTeam::where('event_participant_id', $t3)->first();

    expect($pt2->rank)->toBe(1);
    expect($pt1->rank)->toBe(2);
    expect($pt3->rank)->toBe(3);
});

test('ScheduleCalculator tidak menaruh dua game di court yang sama pada slot sama, menghormati break, dan memberi jeda minimum tim', function (): void {
    $user = User::factory()->create();
    $startDate = Carbon::create(2026, 10, 15, 8, 0, 0);

    $event = Event::factory()->create([
        'user_id' => $user->id,
        'starts_at' => $startDate,
    ]);
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    $venueId = DB::table('venues')->insertGetId([
        'name' => 'GOR Bulungan',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $court1Id = DB::table('venue_courts')->insertGetId([
        'venue_id' => $venueId,
        'name' => 'Court 1',
        'is_indoor' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $court2Id = DB::table('venue_courts')->insertGetId([
        'venue_id' => $venueId,
        'name' => 'Court 2',
        'is_indoor' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ec1 = EventCourt::create(['event_id' => $event->id, 'venue_court_id' => $court1Id]);
    $ec2 = EventCourt::create(['event_id' => $event->id, 'venue_court_id' => $court2Id]);

    // Break at 12:00 - 13:00 (Lunch break)
    EventScheduleBreak::create([
        'event_id' => $event->id,
        'date' => '2026-10-15',
        'start_time' => '12:00:00',
        'end_time' => '13:00:00',
        'label' => 'Lunch Break',
    ]);

    $settings = EventScheduleSetting::create([
        'event_id' => $event->id,
        'start_time' => '08:00:00',
        'end_time_daily' => '17:00:00',
        'break_between_games' => 15,
        'durations' => ['default' => 60, 'min_team_rest' => 60],
    ]);

    $teams = EventParticipant::factory()->count(4)->create([
        'event_id' => $event->id,
        'event_category_id' => $category->id,
    ]);

    // Create 6 games with known teams
    $games = collect([
        Game::create(['event_id' => $event->id, 'event_category_id' => $category->id, 'team_a_id' => $teams[0]->id, 'team_b_id' => $teams[1]->id, 'sort_order' => 1]),
        Game::create(['event_id' => $event->id, 'event_category_id' => $category->id, 'team_a_id' => $teams[2]->id, 'team_b_id' => $teams[3]->id, 'sort_order' => 2]),
        Game::create(['event_id' => $event->id, 'event_category_id' => $category->id, 'team_a_id' => $teams[0]->id, 'team_b_id' => $teams[2]->id, 'sort_order' => 3]),
        Game::create(['event_id' => $event->id, 'event_category_id' => $category->id, 'team_a_id' => $teams[1]->id, 'team_b_id' => $teams[3]->id, 'sort_order' => 4]),
    ]);

    $calculator = new ScheduleCalculator;
    $scheduled = $calculator->calculate($event, $games, collect([$ec1, $ec2]), $settings);

    // 1. Check court exclusivity: no two games on the same court overlap
    $byCourt = $scheduled->groupBy('venue_court_id');
    foreach ($byCourt as $cId => $cGames) {
        $sorted = $cGames->sortBy('game_time')->values();
        for ($i = 0; $i < $sorted->count() - 1; $i++) {
            $currentEnd = Carbon::parse($sorted[$i]->game_time)->addMinutes(60);
            $nextStart = Carbon::parse($sorted[$i + 1]->game_time);
            // Current game end + 15 min break <= nextStart
            expect($nextStart->gte($currentEnd->copy()->addMinutes(15)))->toBeTrue();
        }
    }

    // 2. Check break respect: no game runs during 12:00 - 13:00
    foreach ($scheduled as $g) {
        $gStart = Carbon::parse($g->game_time);
        $gEnd = $gStart->copy()->addMinutes(60);

        $breakStart = Carbon::parse('2026-10-15 12:00:00');
        $breakEnd = Carbon::parse('2026-10-15 13:00:00');

        $overlapsBreak = $gStart->lt($breakEnd) && $gEnd->gt($breakStart);
        expect($overlapsBreak)->toBeFalse();
    }

    // 3. Check team minimum rest: Team 0 plays Game 0 and Game 2
    $t0Games = $scheduled->filter(fn ($g) => $g->team_a_id === $teams[0]->id || $g->team_b_id === $teams[0]->id)
        ->sortBy('game_time')
        ->values();

    for ($i = 0; $i < $t0Games->count() - 1; $i++) {
        $firstGameEnd = Carbon::parse($t0Games[$i]->game_time)->addMinutes(60);
        $secondGameStart = Carbon::parse($t0Games[$i + 1]->game_time);

        // At least 60 min rest
        expect($secondGameStart->gte($firstGameEnd->copy()->addMinutes(60)))->toBeTrue();
    }
});
