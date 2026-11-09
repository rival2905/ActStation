<?php

declare(strict_types=1);

use App\Modules\Scoreboard\Services\BasketballEngine;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('basketball engine provides training state and explicit clock semantics', function (): void {
    $engine = new BasketballEngine;
    $s = $engine->initial('demo', 'Home', 'Away', 1000);
    expect($s['game_clock']['remaining_ms'])->toBe(600000)->and($s['revision'])->toBe(0);
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
    $s = $e->apply($s, 'period.set', ['type' => 'overtime', 'number' => 10], 0);
    $s = $e->apply($s, 'shot_clock.display.set', ['enabled' => false], 0);
    expect($s['period']['number'])->toBe(10)->and($s['shot_clock']['display_enabled'])->toBeFalse();
    $s = $e->apply($s, 'horn.trigger', [], 10);
    expect($s['signal']['kind'])->toBe('horn_manual')->and($s['signal']['expires_at_ms'])->toBe(2010);
    $s = $e->apply($s, 'team_fouls.reset', ['confirmed' => true, 'reason' => 'Periode baru'], 10);
    expect($s['home']['team_fouls'])->toBe(0)->and($s['home']['score'])->toBe(1)->and($s['home']['timeouts_used'])->toBe(1);
});
