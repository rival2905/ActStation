<?php

declare(strict_types=1);

use App\Modules\Scoreboard\Services\BasketballEngine;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('period ledger reopens points and fouls without changing total and uses configured duration', function (): void {
    $e = new BasketballEngine;
    $s = $e->initial('fixture', 'Home', 'Away', 0);
    foreach ([['home', 3], ['home', 2], ['away', 1]] as [$team,$delta]) {
        $s = $e->apply($s, 'score.adjust', compact('team', 'delta'), 0);
    }
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 2], 0);
    foreach ([['home', 3], ['away', 3], ['away', 2]] as [$team,$delta]) {
        $s = $e->apply($s, 'score.adjust', compact('team', 'delta'), 0);
    }
    foreach (['home', 'home', 'home', 'away'] as $team) {
        $s = $e->apply($s, 'team_fouls.adjust', ['team' => $team, 'delta' => 1], 0);
    }
    $s = $e->apply($s, 'period.duration.set', ['duration_ms' => 900000], 0);
    $s = $e->apply($s, 'game_clock.set', ['remaining_ms' => 120000], 0);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 3], 0);
    expect($s['period_history'][1]['home'])->toBe(['score' => 3, 'team_fouls' => 3])->and($s['home']['score'])->toBe(8)->and($s['away']['score'])->toBe(6)->and($s['home']['team_fouls'])->toBe(0)->and($s['game_clock']['remaining_ms'])->toBe(900000);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 2], 0);
    expect($s['period_history'])->toHaveCount(1)->and($s['period_active']['away']['score'])->toBe(5)->and($s['home']['team_fouls'])->toBe(3)->and($s['home']['score'])->toBe(8)->and($s['game_clock']['remaining_ms'])->toBe(120000);
    $s = $e->apply($s, 'game_clock.reset', [], 0);
    expect($s['game_clock']['remaining_ms'])->toBe(900000)->and($s['period_history'])->toHaveCount(1)->and($s['home']['score'])->toBe(8);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 3], 0);
    expect($s['period_history'])->toHaveCount(2);
    $s = $e->apply($s, 'score.adjust', ['team' => 'home', 'delta' => 1], 0);
    $s['period_history'][0]['period'] = ['number' => 1, 'type' => 'regulation'];
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 2], 0);
    expect($s['home']['score'])->toBe(8)->and($s['away']['score'])->toBe(6);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 1], 0);
    expect($s['period_history'])->toBe([])->and($s['home']['score'])->toBe(5)->and($s['away']['score'])->toBe(1)->and($s['period_active']['home']['score'])->toBe(5);
});

test('restored first period converts legacy baseline into editable active points', function (): void {
    $e = new BasketballEngine;
    $s = $e->initial('fixture', 'Home', 'Away', 0);
    $s['home']['score'] = 43;
    $s['away']['score'] = 42;
    $s['unallocated_score'] = ['home' => 43, 'away' => 42];
    $s = $e->materialize($s, 0);
    expect($s['period_active']['home']['score'])->toBe(43)->and($s['period_active']['away']['score'])->toBe(42)->and($s['unallocated_score'])->toBe(['home' => 0, 'away' => 0]);
    $s = $e->apply($s, 'score.adjust', ['team' => 'home', 'delta' => -1], 0);
    expect($s['home']['score'])->toBe(42)->and($s['period_active']['home']['score'])->toBe(42);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 2], 0);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 1], 0);
    expect($s['period_history'])->toBe([])->and($s['period_active']['home']['score'])->toBe(42);
});

test('period ledger preserves legacy unknown totals overtime foul carry and reset boundaries', function (): void {
    $e = new BasketballEngine;
    $s = $e->initial('fixture', 'Home', 'Away', 0);
    $s['period'] = ['type' => 'regulation', 'number' => 2];
    unset($s['period_active'],$s['period_history'],$s['unallocated_score']);
    $s['home']['score'] = 8;
    $s['away']['score'] = 6;
    $s['home']['team_fouls'] = 3;
    $s = $e->materialize($s, 0);
    expect($s['unallocated_score'])->toBe(['home' => 8, 'away' => 6])->and($s['period_active']['home']['score'])->toBe(0);
    expect(fn () => $e->apply($s, 'score.adjust', ['team' => 'home', 'delta' => -1], 0))->toThrow(ValidationException::class);
    for ($n = 3; $n <= 4; $n++) {
        $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => $n], 0);
    }
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'home', 'delta' => 1], 0);
    $s = $e->apply($s, 'period.set', ['type' => 'overtime', 'number' => 1], 0);
    expect($s['home']['team_fouls'])->toBe(1)->and($s['period_active']['home']['team_fouls'])->toBe(0)->and($s['game_clock']['remaining_ms'])->toBe(300000);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 4], 0);
    expect($s['home']['team_fouls'])->toBe(1)->and($s['period_history'])->toHaveCount(2);
    expect($e->apply($s, 'period.set', $s['period'], 0))->toBe($s);
    expect(fn () => $e->apply($s, 'period.set', ['type' => 'overtime', 'number' => 2], 0))->toThrow(ValidationException::class);
    $s = $e->apply($s, 'game_clock.start', [], 0);
    foreach (['game_clock.reset' => [], 'period.duration.set' => ['duration_ms' => 900000], 'period.set' => ['type' => 'overtime', 'number' => 1]] as $type => $payload) {
        expect(fn () => $e->apply($s, $type, $payload, 1))->toThrow(ValidationException::class);
    }
    $s = $e->apply($s, 'game_clock.stop', [], 1000);
    $s = $e->apply($s, 'period.duration.set', ['duration_ms' => 900000], 1000);
    $s = $e->apply($s, 'session.reset', ['confirmed' => true, 'reason' => 'Latihan baru'], 1000);
    expect($s['period_history'])->toBe([])->and($s['unallocated_score']['home'])->toBe(0)->and($s['home']['score'])->toBe(0)->and($s['game_clock']['remaining_ms'])->toBe(900000);
});

test('basketball engine provides training state and explicit clock semantics', function (): void {
    $engine = new BasketballEngine;
    $s = $engine->initial('demo', 'Home', 'Away', 1000);
    expect($s['game_clock']['remaining_ms'])->toBe(600000)->and($s['revision'])->toBe(0);
    expect($s['period_history'])->toBe([]);
    expect($s['period_active'])->toBe([
        'period' => ['type' => 'regulation', 'number' => 1],
        'home' => ['score' => 0, 'team_fouls' => 0],
        'away' => ['score' => 0, 'team_fouls' => 0],
    ]);
    expect($s['unallocated_score'])->toBe(['home' => 0, 'away' => 0]);
    $s = $engine->apply($s, 'game_clock.start', [], 1000);
    expect($s['shot_clock']['running'])->toBeFalse();
    $s = $engine->apply($s, 'shot_clock.start', [], 1000);
    $s = $engine->materialize($s, 2500);
    expect($s['game_clock']['remaining_ms'])->toBe(598500)->and($s['shot_clock']['remaining_ms'])->toBe(22500);
    $s = $engine->apply($s, 'game_clock.stop', [], 2500);
    expect($s['shot_clock']['running'])->toBeFalse();
});

test('basketball fixtures preserve chronological clock semantics', function (): void {
    $engine = new BasketballEngine;
    foreach (json_decode(file_get_contents(__DIR__.'/../Fixtures/scoreboard/basketball.v1.json'), true, flags: JSON_THROW_ON_ERROR) as $fixture) {
        $s = $engine->initial('fixture', 'Home', 'Away', 0);
        foreach ($fixture['commands'] as [$time, $type, $payload]) {
            $s = $engine->apply($s, $type, $payload, $time);
        }
        $s = $engine->materialize($s, $fixture['at']);
        foreach ($fixture['expected'] as $key => $value) {
            expect(data_get($s, $key), $fixture['name'].' '.$key)->toBe($value);
        }
    }
});

test('basketball rejects malformed unsafe and out of range commands', function (string $type, array $payload): void {
    $engine = new BasketballEngine;
    $s = $engine->initial('fixture', 'Home', 'Away', 0);
    expect(fn () => $engine->apply($s, $type, $payload, 0))->toThrow(ValidationException::class);
})->with([
    ['score.adjust', ['team' => 'home', 'delta' => -1]],
    ['score.adjust', ['team' => 'home', 'delta' => '2']],
    ['score.adjust', ['team' => [], 'delta' => 2]],
    ['score.adjust', ['team' => 'home', 'delta' => 2, 'extra' => true]],
    ['shot_clock.start', []], ['shot_clock.reset', ['duration_ms' => 12000]],
    ['shot_clock.set', ['remaining_ms' => 99001]], ['game_clock.set', ['remaining_ms' => -1]],
    ['shot_clock.display.set', ['enabled' => 'false']], ['period.set', ['type' => 'regulation', 'number' => 5]],
    ['period.set', ['type' => 'overtime', 'number' => 0]], ['session.reset', ['confirmed' => false, 'reason' => 'Reset']],
    ['team_fouls.reset', ['confirmed' => true, 'reason' => '']], ['unknown', []],
]);

test('basketball supports bounded counts period blank horn and reset metadata', function (): void {
    $e = new BasketballEngine;
    $s = $e->initial('fixture', 'Home', 'Away', 0);
    foreach (['score.adjust', 'team_fouls.adjust', 'timeouts_used.adjust'] as $type) {
        $s = $e->apply($s, $type, ['team' => 'home', 'delta' => 1], 0);
    }
    $s = $e->apply($s, 'possession.set', ['team' => 'home'], 0);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 2], 0);
    $s = $e->apply($s, 'shot_clock.display.set', ['enabled' => false], 0);
    expect($s['period']['number'])->toBe(2)->and($s['shot_clock']['display_enabled'])->toBeFalse();
    $s = $e->apply($s, 'horn.trigger', [], 10);
    expect($s['signal']['kind'])->toBe('horn_manual')->and($s['signal']['expires_at_ms'])->toBe(2010);
    expect(fn () => $e->apply($s, 'team_fouls.reset', ['confirmed' => true, 'reason' => 'Periode baru'], 10))->toThrow(ValidationException::class);
    expect($s['home']['team_fouls'])->toBe(0)->and($s['home']['score'])->toBe(1)->and($s['home']['timeouts_used'])->toBe(1);
});

test('period correct validates payload running clocks and closed period existence', function (): void {
    $e = new BasketballEngine;
    $s = $e->initial('fixture', 'Home', 'Away', 0);

    // Initial is in Q1. Move to Q2 so Q1 is in history.
    $s = $e->apply($s, 'score.adjust', ['team' => 'home', 'delta' => 3], 0);
    $s = $e->apply($s, 'score.adjust', ['team' => 'away', 'delta' => 2], 0);
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'home', 'delta' => 1], 0);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 2], 0);

    $basePayload = [
        'type' => 'regulation',
        'number' => 1,
        'home_score' => 5,
        'away_score' => 4,
        'home_fouls' => 2,
        'away_fouls' => 1,
        'reason' => 'Salah input',
        'note' => 'Koreksi Q1',
    ];

    // Invalid payload structure / keys
    expect(fn () => $e->apply($s, 'period.correct', array_diff_key($basePayload, ['note' => '']), 0))->toThrow(ValidationException::class);
    expect(fn () => $e->apply($s, 'period.correct', $basePayload + ['extra' => 1], 0))->toThrow(ValidationException::class);

    // Invalid reason enum
    expect(fn () => $e->apply($s, 'period.correct', array_replace($basePayload, ['reason' => 'Lainnya']), 0))->toThrow(ValidationException::class);
    expect(fn () => $e->apply($s, 'period.correct', array_replace($basePayload, ['reason' => '']), 0))->toThrow(ValidationException::class);

    // Valid reasons
    foreach (['Salah input', 'Keputusan wasit'] as $reason) {
        $testState = $e->apply($s, 'period.correct', array_replace($basePayload, ['reason' => $reason]), 0);
        expect($testState['period_history'][0]['home']['score'])->toBe(5);
    }

    // Invalid note
    expect(fn () => $e->apply($s, 'period.correct', array_replace($basePayload, ['note' => str_repeat('a', 201)]), 0))->toThrow(ValidationException::class);
    expect(fn () => $e->apply($s, 'period.correct', array_replace($basePayload, ['note' => 123]), 0))->toThrow(ValidationException::class);

    // Valid optional notes (null, empty, <=200)
    $sNullNote = $e->apply($s, 'period.correct', array_replace($basePayload, ['note' => null]), 0);
    expect($sNullNote['period_history'][0]['home']['score'])->toBe(5);
    $sEmptyNote = $e->apply($s, 'period.correct', array_replace($basePayload, ['note' => '']), 0);
    expect($sEmptyNote['period_history'][0]['home']['score'])->toBe(5);

    // Invalid scores and fouls bounds / types
    foreach ([
        ['home_score', -1], ['home_score', 1000], ['home_score', '5'],
        ['away_score', -1], ['away_score', 1000], ['away_score', 4.5],
        ['home_fouls', -1], ['home_fouls', 100], ['home_fouls', '2'],
        ['away_fouls', -1], ['away_fouls', 100], ['away_fouls', []],
        ['type', 'halftime'], ['number', 0], ['number', 5],
    ] as [$field, $invalidVal]) {
        expect(fn () => $e->apply($s, 'period.correct', array_replace($basePayload, [$field => $invalidVal]), 0))
            ->toThrow(ValidationException::class);
    }

    // Active period rejection: current is regulation 2
    expect(fn () => $e->apply($s, 'period.correct', array_replace($basePayload, ['number' => 2]), 0))
        ->toThrow(ValidationException::class);

    // Non-existent period rejection: regulation 3 does not exist in history
    expect(fn () => $e->apply($s, 'period.correct', array_replace($basePayload, ['number' => 3]), 0))
        ->toThrow(ValidationException::class);

    // Running clock rejections
    $running = $e->apply($s, 'game_clock.start', [], 0);
    expect(fn () => $e->apply($running, 'period.correct', $basePayload, 0))->toThrow(ValidationException::class);

    $sTimeout = $e->apply($s, 'timeout_clock.start', ['duration_ms' => 30000], 0);
    expect(fn () => $e->apply($sTimeout, 'period.correct', $basePayload, 0))->toThrow(ValidationException::class);

    $sShotRunning = $s;
    $sShotRunning['shot_clock']['running'] = true;
    $sShotRunning['shot_clock']['deadline_ms'] = 24000;
    expect(fn () => $e->apply($sShotRunning, 'period.correct', $basePayload, 0))->toThrow(ValidationException::class);
});

test('period correct adjusts total scores by difference, enforces bounds, leaves active and clocks unchanged', function (): void {
    $e = new BasketballEngine;
    $s = $e->initial('fixture', 'Home', 'Away', 0);

    // Q1: Home 10, Away 8, Fouls Home 2, Away 1
    for ($i = 0; $i < 5; $i++) {
        $s = $e->apply($s, 'score.adjust', ['team' => 'home', 'delta' => 2], 0);
    }
    for ($i = 0; $i < 4; $i++) {
        $s = $e->apply($s, 'score.adjust', ['team' => 'away', 'delta' => 2], 0);
    }
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'home', 'delta' => 1], 0);
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'home', 'delta' => 1], 0);
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'away', 'delta' => 1], 0);

    // Move to Q2
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 2], 0);

    // In Q2: Home scores 3, Away scores 2. Clocks at 600000.
    $s = $e->apply($s, 'score.adjust', ['team' => 'home', 'delta' => 3], 0);
    $s = $e->apply($s, 'score.adjust', ['team' => 'away', 'delta' => 2], 0);
    $s = $e->apply($s, 'game_clock.set', ['remaining_ms' => 450000], 0);
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'home', 'delta' => 1], 0);

    expect($s['home']['score'])->toBe(13);
    expect($s['away']['score'])->toBe(10);
    expect($s['home']['team_fouls'])->toBe(1);
    expect($s['away']['team_fouls'])->toBe(0);
    $activeBefore = $s['period_active'];
    $clocksBefore = ['game' => $s['game_clock'], 'shot' => $s['shot_clock'], 'timeout' => $s['timeout_clock']];
    $unallocatedBefore = $s['unallocated_score'];
    $revBefore = $s['revision'];

    // Correct Q1: change Home from 10 to 12 (+2), Away from 8 to 5 (-3), Fouls Home from 2 to 3, Away from 1 to 4
    $s = $e->apply($s, 'period.correct', [
        'type' => 'regulation',
        'number' => 1,
        'home_score' => 12,
        'away_score' => 5,
        'home_fouls' => 3,
        'away_fouls' => 4,
        'reason' => 'Salah input',
        'note' => 'Perbaikan kuarter 1',
    ], 0);

    // Verification
    expect($s['revision'])->toBe($revBefore + 1);
    expect($s['period_history'][0]['home']['score'])->toBe(12);
    expect($s['period_history'][0]['away']['score'])->toBe(5);
    expect($s['period_history'][0]['home']['team_fouls'])->toBe(3);
    expect($s['period_history'][0]['away']['team_fouls'])->toBe(4);
    expect($s['period_history'][0]['display_fouls'])->toBe(['home' => 3, 'away' => 4]);

    // Total scores adjusted by difference: Home 13 + 2 = 15; Away 10 - 3 = 7
    expect($s['home']['score'])->toBe(15);
    expect($s['away']['score'])->toBe(7);

    // period_active, clocks, unallocated_score unchanged
    expect($s['period_active'])->toBe($activeBefore);
    expect($s['game_clock'])->toBe($clocksBefore['game']);
    expect($s['shot_clock'])->toBe($clocksBefore['shot']);
    expect($s['timeout_clock'])->toBe($clocksBefore['timeout']);
    expect($s['unallocated_score'])->toBe($unallocatedBefore);

    // Active fouls for regulation correction MUST NOT change active fouls
    expect($s['home']['team_fouls'])->toBe(1);
    expect($s['away']['team_fouls'])->toBe(0);

    // Total score bounds: rejecting if difference pushes total out of bounds (0..999)
    expect(fn () => $e->apply($s, 'period.correct', [
        'type' => 'regulation',
        'number' => 1,
        'home_score' => 999, // delta would be 999 - 12 = +987; total would be 15 + 987 = 1002 > 999
        'away_score' => 5,
        'home_fouls' => 3,
        'away_fouls' => 4,
        'reason' => 'Keputusan wasit',
        'note' => null,
    ], 0))->toThrow(ValidationException::class);

    // Stepping back to Q1 recalculates without score loss
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 1], 0);
    expect($s['period_history'])->toHaveCount(0);
    expect($s['period_active']['home']['score'])->toBe(12);
    expect($s['period_active']['away']['score'])->toBe(5);
    expect($s['home']['score'])->toBe(12);
    expect($s['away']['score'])->toBe(5);
    expect($s['home']['team_fouls'])->toBe(3);
    expect($s['away']['team_fouls'])->toBe(4);
});

test('period correct propagates foul delta in overtime carry-over across history and current board', function (): void {
    $e = new BasketballEngine;
    $s = $e->initial('fixture', 'Home', 'Away', 0);

    // Advance Q1 -> Q2 -> Q3 -> Q4
    for ($n = 2; $n <= 4; $n++) {
        $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => $n], 0);
    }

    // In Q4: home commits 4 fouls, away commits 2 fouls
    for ($i = 0; $i < 4; $i++) {
        $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'home', 'delta' => 1], 0);
    }
    for ($i = 0; $i < 2; $i++) {
        $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'away', 'delta' => 1], 0);
    }

    // Advance Q4 -> OT1 (foul carry-over into OT1)
    $s = $e->apply($s, 'period.set', ['type' => 'overtime', 'number' => 1], 0);
    expect($s['home']['team_fouls'])->toBe(4);
    expect($s['away']['team_fouls'])->toBe(2);

    // In OT1: home commits 2 fouls, away commits 1 foul
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'home', 'delta' => 1], 0);
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'home', 'delta' => 1], 0);
    $s = $e->apply($s, 'team_fouls.adjust', ['team' => 'away', 'delta' => 1], 0);
    expect($s['home']['team_fouls'])->toBe(6);
    expect($s['away']['team_fouls'])->toBe(3);

    // Advance OT1 -> OT2
    $s = $e->apply($s, 'period.set', ['type' => 'overtime', 'number' => 2], 0);
    expect($s['home']['team_fouls'])->toBe(6);
    expect($s['away']['team_fouls'])->toBe(3);

    // History:
    // [0]: Q1, [1]: Q2, [2]: Q3, [3]: Q4 (display 4, 2), [4]: OT1 (display 6, 3)
    // Active: OT2 (board 6, 3)

    // Test 1: Ordinary regulation correction (Q3) while in OT2 does NOT propagate to OT or board
    $s = $e->apply($s, 'period.correct', [
        'type' => 'regulation',
        'number' => 3,
        'home_score' => 0,
        'away_score' => 0,
        'home_fouls' => 5,
        'away_fouls' => 5,
        'reason' => 'Salah input',
        'note' => null,
    ], 0);
    expect($s['period_history'][2]['display_fouls'])->toBe(['home' => 5, 'away' => 5]);
    expect($s['period_history'][3]['display_fouls'])->toBe(['home' => 4, 'away' => 2]); // Q4 untouched
    expect($s['period_history'][4]['display_fouls'])->toBe(['home' => 6, 'away' => 3]); // OT1 untouched
    expect($s['home']['team_fouls'])->toBe(6); // Active board untouched
    expect($s['away']['team_fouls'])->toBe(3);

    // Test 2: Target Q4 correction while in OT propagates to later OT (OT1) and current board
    // Q4 fouls: Home from 4 to 5 (+1), Away from 2 to 1 (-1)
    $s = $e->apply($s, 'period.correct', [
        'type' => 'regulation',
        'number' => 4,
        'home_score' => 0,
        'away_score' => 0,
        'home_fouls' => 5,
        'away_fouls' => 1,
        'reason' => 'Keputusan wasit',
        'note' => 'Wasit batalkan foul away, tambah foul home',
    ], 0);
    expect($s['period_history'][3]['display_fouls'])->toBe(['home' => 5, 'away' => 1]);
    expect($s['period_history'][4]['display_fouls'])->toBe(['home' => 7, 'away' => 2]); // OT1 carried +1 / -1
    expect($s['home']['team_fouls'])->toBe(7); // Board carried +1
    expect($s['away']['team_fouls'])->toBe(2); // Board carried -1

    // Test 3: Target OT1 correction while in OT2 propagates to current board
    // OT1 fouls: Home from 2 to 3 (+1), Away from 1 to 1 (0)
    $s = $e->apply($s, 'period.correct', [
        'type' => 'overtime',
        'number' => 1,
        'home_score' => 0,
        'away_score' => 0,
        'home_fouls' => 3,
        'away_fouls' => 1,
        'reason' => 'Salah input',
        'note' => null,
    ], 0);
    expect($s['period_history'][4]['home']['team_fouls'])->toBe(3);
    expect($s['period_history'][4]['display_fouls'])->toBe(['home' => 8, 'away' => 2]);
    expect($s['home']['team_fouls'])->toBe(8); // Board carried +1
    expect($s['away']['team_fouls'])->toBe(2);

    // Test 4: Overtime carry-over rejects if foul delta pushes current board beyond max (99)
    expect(fn () => $e->apply($s, 'period.correct', [
        'type' => 'overtime',
        'number' => 1,
        'home_score' => 0,
        'away_score' => 0,
        'home_fouls' => 95, // delta would be +92; board would become 8 + 92 = 100 > 99
        'away_fouls' => 1,
        'reason' => 'Salah input',
        'note' => null,
    ], 0))->toThrow(ValidationException::class);
});

test('period correct preserves legacy unallocated scores without loss', function (): void {
    $e = new BasketballEngine;
    $s = $e->initial('fixture', 'Home', 'Away', 0);
    $s['period'] = ['type' => 'regulation', 'number' => 2];
    unset($s['period_active'], $s['period_history'], $s['unallocated_score']);
    $s['home']['score'] = 14;
    $s['away']['score'] = 10;
    $s = $e->materialize($s, 0);

    // unallocated is now home 14, away 10
    expect($s['unallocated_score'])->toBe(['home' => 14, 'away' => 10]);

    // Q2: Home 2, Away 3
    $s = $e->apply($s, 'score.adjust', ['team' => 'home', 'delta' => 2], 0);
    $s = $e->apply($s, 'score.adjust', ['team' => 'away', 'delta' => 3], 0);
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 3], 0);

    expect($s['home']['score'])->toBe(16);
    expect($s['away']['score'])->toBe(13);

    // Correct Q2 from Home 2 to 4 (+2), Away from 3 to 1 (-2)
    $s = $e->apply($s, 'period.correct', [
        'type' => 'regulation',
        'number' => 2,
        'home_score' => 4,
        'away_score' => 1,
        'home_fouls' => 0,
        'away_fouls' => 0,
        'reason' => 'Salah input',
        'note' => 'Preserve unallocated check',
    ], 0);

    expect($s['unallocated_score'])->toBe(['home' => 14, 'away' => 10]);
    expect($s['home']['score'])->toBe(18);
    expect($s['away']['score'])->toBe(11);
    expect($s['period_history'][0]['home']['score'])->toBe(4);
    expect($s['period_history'][0]['away']['score'])->toBe(1);

    // Step back to Q2: total should be unallocated (14/10) + Q2 (4/1) = 18/11
    $s = $e->apply($s, 'period.set', ['type' => 'regulation', 'number' => 2], 0);
    expect($s['unallocated_score'])->toBe(['home' => 14, 'away' => 10]);
    expect($s['home']['score'])->toBe(18);
    expect($s['away']['score'])->toBe(11);
    expect($s['period_active']['home']['score'])->toBe(4);
    expect($s['period_active']['away']['score'])->toBe(1);
});
