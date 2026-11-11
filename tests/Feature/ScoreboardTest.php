<?php

declare(strict_types=1);

use App\Contracts\CommunityLookup;
use App\Contracts\ScoreboardSports;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\Scoreboard\Models\ScoreboardCommand;
use App\Modules\Scoreboard\Models\ScoreboardSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    expect(config('database.connections.mysql.database'))->toBe('db_actstation_test');
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->owner = User::factory()->create(['is_active' => true]);
    $this->owner->assignRole('community_manager');
    $this->community = Community::factory()->create(['is_active' => true, 'sport_category_id' => null]);
    CommunityOfficial::create(['community_id' => $this->community->id, 'user_id' => $this->owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->sport = SportCategory::firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
    $this->actingAs($this->owner)->withSession(['current_community_id' => $this->community->id]);
    $this->createQuick = fn (array $extra = []) => $this->postJson('/api/v1/scoreboard/sessions', array_replace([
        'sport_category_id' => $this->sport->id, 'mode' => 'quick', 'kind' => 'practice',
        'panel_community_id' => $this->community->id,
    ], $extra));
});

test('manager creates a quick session without sports history or profile sport restriction', function (): void {
    $response = ($this->createQuick)()->assertCreated()->assertJsonPath('data.mode', 'quick')->assertJsonPath('data.result', null)
        ->assertJsonPath('data.state.schema_version', 'basketball.v1')->assertJsonPath('data.state.home.score', 0);
    $session = ScoreboardSession::findOrFail($response->json('data.id'));
    expect($session->community_id)->toBe($this->community->id)->and($session->result)->toBeNull();
    expect($this->community->fresh()->sport_category_id)->toBeNull();
    $response->assertJsonMissingPath('data.state.game_clock.deadline_ms');
});

function scoreboardCommand(array $s, string $type, array $payload = []): array
{
    return ['panel_community_id' => $s['community_id'], 'command_id' => (string) Str::uuid(), 'session_id' => $s['id'],
        'schema_version' => 'basketball.v1', 'authority_epoch' => $s['state']['authority_epoch'], 'expected_revision' => $s['state']['revision'],
        'type' => $type, 'payload' => $payload, 'expires_at' => now()->addSeconds(4)->toISOString()];
}

test('period records persist reopen deduplicate and finish without changing cumulative score', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $apply = function (string $type, array $payload = []) use (&$s, $url): void {
        $s = $this->postJson($url.'/commands', scoreboardCommand($s, $type, $payload))->assertOk()->assertJsonPath('data.status', 'applied')->json('data.snapshot');
    };
    $apply('score.adjust', ['team' => 'home', 'delta' => 3]);
    $apply('score.adjust', ['team' => 'home', 'delta' => 2]);
    $apply('score.adjust', ['team' => 'away', 'delta' => 1]);
    $apply('period.set', ['type' => 'regulation', 'number' => 2]);
    $apply('score.adjust', ['team' => 'home', 'delta' => 3]);
    $apply('score.adjust', ['team' => 'away', 'delta' => 3]);
    $apply('score.adjust', ['team' => 'away', 'delta' => 2]);
    $apply('team_fouls.adjust', ['team' => 'home', 'delta' => 1]);
    $input = scoreboardCommand($s, 'period.set', ['type' => 'regulation', 'number' => 3]);
    $ack = $this->postJson($url.'/commands', $input)->assertOk()->assertJsonPath('data.status', 'applied');
    $this->postJson($url.'/commands', $input)->assertExactJson($ack->json());
    $s = $this->getJson($url)->assertOk()->assertJsonPath('data.state.period_history.1.home.score', 3)->assertJsonPath('data.state.period_history.1.away.score', 5)->json('data');
    expect(ScoreboardSession::findOrFail($s['id'])->state['period_history'])->toHaveCount(2);
    $apply('period.set', ['type' => 'regulation', 'number' => 2]);
    expect($s['state']['period_history'])->toHaveCount(1);
    expect($s['state']['period_changes'][0]['action'])->toBe('rollback');
    expect($s['state']['period_changes'][0]['before']['period']['number'])->toBe(3);
    expect($s['state']['period_changes'][0]['operator'])->toBe($this->owner->name);
    $public = URL::signedRoute('scoreboard.sessions.display-snapshot', ['session' => $s['id']]);
    $this->getJson($public)->assertOk()->assertJsonMissingPath('data.state.period_changes');
    $this->getJson($url)->assertJsonPath('data.state.home.score', 8)->assertJsonPath('data.state.away.score', 6)->assertJsonPath('data.state.home.team_fouls', 1)->assertJsonPath('data.state.period_active.away.score', 5);
    $finish = ['panel_community_id' => $this->community->id, 'command_id' => (string) Str::uuid(), 'expected_revision' => $s['state']['revision'], 'record_result' => true, 'home_name' => 'H', 'away_name' => 'A', 'kind' => 'friendly'];
    $response = $this->postJson($url.'/finish', $finish)->assertOk()->assertJsonPath('data.result.home.score', 8)->assertJsonPath('data.result.period_history.1.away.score', 5);
    expect($response->json('data.result.period_history'))->toHaveCount(2);
    expect(ScoreboardSession::findOrFail($s['id'])->result['period_history'])->toHaveCount(2);
});

test('historical correction updates total and private audit without changing active period', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $apply = function (string $type, array $payload) use (&$s, $url): void {
        $s = $this->postJson($url.'/commands', scoreboardCommand($s, $type, $payload))->assertOk()->assertJsonPath('data.status', 'applied')->json('data.snapshot');
    };
    $apply('score.adjust', ['team' => 'home', 'delta' => 3]);
    $apply('period.set', ['type' => 'regulation', 'number' => 2]);
    $apply('score.adjust', ['team' => 'home', 'delta' => 2]);
    $input = scoreboardCommand($s, 'period.correct', ['type' => 'regulation', 'number' => 1, 'home_score' => 1, 'away_score' => 0, 'home_fouls' => 2, 'away_fouls' => 0, 'reason' => 'Keputusan wasit', 'note' => 'Koreksi meja']);
    $response = $this->postJson($url.'/commands', $input)->assertOk()->assertJsonPath('data.status', 'applied');
    $this->postJson($url.'/commands', $input)->assertExactJson($response->json());
    $s = $this->getJson($url)->assertOk()->assertJsonPath('data.state.home.score', 3)->assertJsonPath('data.state.period_active.home.score', 2)->assertJsonPath('data.state.period.number', 2)->assertJsonPath('data.state.home.team_fouls', 0)->json('data');
    expect($s['state']['period_changes'])->toHaveCount(1);
    expect($s['state']['period_changes'][0]['before']['records'][0]['home']['score'])->toBe(3);
    expect($s['state']['period_changes'][0]['after']['records'][0]['home']['score'])->toBe(1);
    $apply('period.set', ['type' => 'regulation', 'number' => 1]);
    $apply('period.set', ['type' => 'regulation', 'number' => 2]);
    expect($s['state']['period_active']['home']['score'])->toBe(0);
    $apply('period.set', ['type' => 'regulation', 'number' => 3]);
    expect($s['state']['period_history'][1]['home']['score'])->toBe(0);
    $public = URL::signedRoute('scoreboard.sessions.display-snapshot', ['session' => $s['id']]);
    $this->getJson($public)->assertOk()->assertJsonPath('data.state.home.score', 1)->assertJsonMissingPath('data.state.period_changes');
});

test('legacy period rollback restores recorded receipt snapshot and public display', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $s = $this->postJson($url.'/commands', scoreboardCommand($s, 'score.adjust', ['team' => 'home', 'delta' => 3]))->assertOk()->json('data.snapshot');
    $model = ScoreboardSession::findOrFail($s['id']);
    $legacy = $model->state;
    $legacy['period'] = ['type' => 'regulation', 'number' => 2];
    $legacy['home']['score'] = 8;
    $legacy['revision']++;
    unset($legacy['period_active'],$legacy['period_history'],$legacy['unallocated_score']);
    $model->update(['state' => $legacy]);
    $s = $this->getJson($url)->assertOk()->json('data');
    $this->postJson($url.'/commands', scoreboardCommand($s, 'period.set', ['type' => 'regulation', 'number' => 1]))->assertOk()->assertJsonPath('data.status', 'applied')->assertJsonPath('data.snapshot.state.home.score', 3);
    $public = URL::signedRoute('scoreboard.sessions.display-snapshot', ['session' => $s['id']]);
    $this->getJson($public)->assertOk()->assertJsonPath('data.state.period.number', 1)->assertJsonPath('data.state.home.score', 3);
});

test('commands deduplicate and reject stale conflicting expired invalid and wrong epoch input', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $input = scoreboardCommand($s, 'score.adjust', ['team' => 'home', 'delta' => 2]);
    $first = $this->postJson($url.'/commands', $input)->assertOk()->assertJsonPath('data.status', 'applied')->json();
    $this->postJson($url.'/commands', $input)->assertOk()->assertExactJson($first);
    $this->getJson($url.'/commands/'.$input['command_id'])->assertOk()->assertExactJson($first);
    $changed = $input;
    $changed['payload']['delta'] = 3;
    $this->postJson($url.'/commands', $changed)->assertConflict();
    $input['command_id'] = (string) Str::uuid();
    $this->postJson($url.'/commands', $input)->assertOk()->assertJsonPath('data.status', 'rejected');
    $s = $this->getJson($url)->assertOk()->assertJsonPath('data.state.home.score', 2)->json('data');
    foreach (['expired', 'future', 'epoch', 'invalid', 'wrong_session'] as $case) {
        $bad = scoreboardCommand($s, 'score.adjust', ['team' => 'home', 'delta' => 1]);
        if ($case === 'expired') {
            $bad['expires_at'] = now()->subSecond()->toISOString();
        }
        if ($case === 'future') {
            $bad['expires_at'] = now()->addMinute()->toISOString();
        }
        if ($case === 'epoch') {
            $bad['authority_epoch']++;
        }
        if ($case === 'invalid') {
            $bad['payload']['delta'] = 500;
        }
        if ($case === 'wrong_session') {
            $bad['session_id'] = (string) Str::uuid();
        }
        $this->postJson($url.'/commands', $bad)->assertOk()->assertJsonPath('data.status', $case === 'expired' ? 'expired' : 'rejected');
    }
    $this->getJson($url)->assertOk()->assertJsonPath('data.state.home.score', 2);
});

test('quick optional recording finalizes same state once with private nonofficial history', function (bool $record): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $s = $this->postJson($url.'/commands', scoreboardCommand($s, 'score.adjust', ['team' => 'away', 'delta' => 3]))->assertOk()->json('data.snapshot');
    $finish = ['panel_community_id' => $this->community->id, 'command_id' => (string) Str::uuid(), 'expected_revision' => $s['state']['revision'],
        'record_result' => $record, 'home_name' => 'Latihan A', 'away_name' => 'Latihan B', 'kind' => 'friendly'];
    $response = $this->postJson($url.'/finish', $finish)->assertOk()->assertJsonPath('data.status', 'finished');
    if ($record) {
        $response->assertJsonPath('data.result.period_history.0.away.score', 3)->assertJsonPath('data.result.unallocated_score.home', 0)->assertJsonPath('data.result.away.score', 3)->assertJsonPath('data.result.visibility', 'private')->assertJsonPath('data.result.status', 'recorded');
    } else {
        $response->assertJsonPath('data.result', null);
    }
    $this->postJson($url.'/finish', $finish)->assertOk()->assertExactJson($response->json());
    expect(ScoreboardSession::where('community_id', $this->community->id)->count())->toBe(1);
    $this->get('/panel/scoreboard')->assertOk()->assertViewHas('history', fn ($history) => $history->total() === (int) $record)
        ->assertViewHas('activeSessions', fn ($sessions) => $sessions->isEmpty());
    $finish['command_id'] = (string) Str::uuid();
    $finish['record_result'] = true;
    $finish['expected_revision']++;
    $this->postJson($url.'/finish', $finish)->assertConflict();
    $this->postJson($url.'/commands', scoreboardCommand($response->json('data'), 'horn.trigger'))->assertOk()->assertJsonPath('data.status', 'rejected');
})->with([true, false]);

test('live clocks require stop and stale finalization cannot bypass expiry revision', function (): void {
    $this->freezeTime();
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $s = $this->postJson($url.'/commands', scoreboardCommand($s, 'game_clock.set', ['remaining_ms' => 1000]))->assertOk()->json('data.snapshot');
    $s = $this->postJson($url.'/commands', scoreboardCommand($s, 'game_clock.start'))->assertOk()->json('data.snapshot');
    $finish = ['panel_community_id' => $this->community->id, 'command_id' => (string) Str::uuid(), 'expected_revision' => $s['state']['revision'], 'record_result' => false];
    $this->postJson($url.'/finish', $finish)->assertUnprocessable()->assertJsonValidationErrors('clocks');
    $this->travel(2)->seconds();
    $this->getJson($url)->assertOk()->assertJsonPath('data.state.game_clock.remaining_ms', 0)->assertJsonPath('data.state.game_clock.running', false);
    $this->postJson($url.'/finish', $finish)->assertConflict();
});

test('unsupported sports recorded mode malformed input and forged scores fail validation', function (): void {
    $futsal = SportCategory::firstOrCreate(['slug' => 'futsal'], ['name' => 'Futsal', 'is_active' => true]);
    ($this->createQuick)(['sport_category_id' => $futsal->id])->assertUnprocessable()->assertJsonValidationErrors('sport_category_id');
    ($this->createQuick)(['sport_category_id' => []])->assertUnprocessable();
    ($this->createQuick)(['schema_version' => 'futsal.v1'])->assertUnprocessable();
    ($this->createQuick)(['mode' => 'recorded'])->assertUnprocessable()->assertJsonValidationErrors(['home_name', 'away_name']);
    $s = ($this->createQuick)(['mode' => 'recorded', 'home_name' => 'A', 'away_name' => 'B'])->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'].'/finish';
    $finish = ['panel_community_id' => $this->community->id, 'command_id' => (string) Str::uuid(), 'expected_revision' => 0, 'record_result' => false];
    $this->postJson($url, $finish)->assertUnprocessable()->assertJsonValidationErrors('record_result');
    $finish = array_replace($finish, ['record_result' => true, 'home_name' => [], 'away_name' => 'B', 'kind' => 'practice', 'score' => 99]);
    $this->postJson($url, $finish)->assertUnprocessable()->assertJsonValidationErrors(['home_name', 'score']);
});

test('foreign switched revoked inactive and nonmanager access cannot read sessions or receipts', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $input = scoreboardCommand($s, 'horn.trigger');
    $this->postJson($url.'/commands', $input)->assertOk();
    $foreign = Community::factory()->create(['is_active' => true]);
    CommunityOfficial::create(['community_id' => $foreign->id, 'user_id' => $this->owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->withSession(['current_community_id' => $foreign->id]);
    $this->getJson($url)->assertNotFound();
    $this->getJson($url.'/commands/'.$input['command_id'])->assertNotFound();
    $this->postJson($url.'/commands', $input)->assertUnprocessable();
    $this->withSession(['current_community_id' => $this->community->id]);
    $this->owner->update(['is_active' => false]);
    $this->getJson($url)->assertForbidden();
    $this->owner->update(['is_active' => true]);
    CommunityOfficial::where('user_id', $this->owner->id)->update(['ended_at' => today()]);
    $this->getJson($url)->assertForbidden();
    $this->actingAs(User::factory()->create(['is_active' => true]))->get('/panel/scoreboard')->assertForbidden();
});

test('reset keeps operator reason in private receipt without leaking audit into snapshots', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $input = scoreboardCommand($s, 'session.reset', ['confirmed' => true, 'reason' => 'Salah input sesi latihan']);
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $this->postJson($url.'/commands', $input)->assertOk()->assertJsonPath('data.status', 'applied');
    $row = ScoreboardCommand::where('command_id', $input['command_id'])->firstOrFail();
    expect($row->audit['reason'] ?? null)->toBe('Salah input sesi latihan');
    $this->getJson($url)->assertOk()->assertJsonMissingPath('data.audit');
});

test('elapsed polls never persist ticks or change revision and guests cannot use API', function (): void {
    $this->freezeTime();
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = '/api/v1/scoreboard/sessions/'.$s['id'];
    $s = $this->postJson($url.'/commands', scoreboardCommand($s, 'game_clock.start'))->assertOk()->json('data.snapshot');
    $stored = ScoreboardSession::findOrFail($s['id'])->state;
    $this->travel(1)->seconds();
    $this->getJson($url)->assertOk()->assertJsonPath('data.state.game_clock.remaining_ms', 599000)->assertJsonPath('data.state.revision', $s['state']['revision']);
    expect(ScoreboardSession::findOrFail($s['id'])->state)->toBe($stored);
    auth()->forgetGuards();
    $this->app['session']->flush();
    $this->getJson($url)->assertUnauthorized();
});

test('signed display opens for guests and supplies a private-data-free live snapshot', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $url = URL::signedRoute('panel.scoreboard.display', ['session' => $s['id'], 'viewMode' => 'main']);
    $this->get('/panel/scoreboard/sessions/'.$s['id'])->assertOk()->assertSee('href="'.$url.'"', false);
    expect($url)->not->toContain('expires=');
    auth()->forgetGuards();
    $this->app['session']->flush();

    $page = $this->get($url)->assertOk()->assertSee('data-readonly="true"', false)
        ->assertDontSee('navbar-vertical', false)->assertDontSee('>Konsol</a>', false)
        ->assertHeader('Referrer-Policy', 'no-referrer');
    preg_match('/data-snapshot-url="([^"]+)"/', $page->getContent(), $match);
    expect($match[1] ?? null)->not->toBeNull();
    $snapshotUrl = html_entity_decode($match[1], ENT_QUOTES);
    $this->getJson($snapshotUrl)->assertOk()->assertJsonPath('data.state.home.score', 0)
        ->assertJsonMissingPath('data.result')->assertJsonMissingPath('data.community_id')
        ->assertJsonMissingPath('data.location')->assertJsonMissingPath('data.created_by')
        ->assertJsonMissingPath('data.state.game_clock.deadline_ms');
});

test('display signatures cannot be omitted tampered or reused for control and private snapshots', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $other = ($this->createQuick)()->assertCreated()->json('data');
    $display = URL::signedRoute('panel.scoreboard.display', ['session' => $s['id'], 'viewMode' => 'main']);
    $snapshot = URL::signedRoute('scoreboard.sessions.display-snapshot', ['session' => $s['id']]);
    $command = scoreboardCommand($s, 'score.adjust', ['team' => 'home', 'delta' => 3]);
    auth()->forgetGuards();
    $this->app['session']->flush();

    $this->get(strtok($display, '?'))->assertForbidden();
    $this->getJson(strtok($snapshot, '?'))->assertForbidden();
    foreach ([$display, $snapshot] as $url) {
        $this->get(str_replace($s['id'], $other['id'], $url))->assertForbidden();
        $this->get($url.'&extra=1')->assertForbidden();
    }
    $this->get(str_replace('/main?', '/shot-left?', $display))->assertForbidden();
    $private = '/api/v1/scoreboard/sessions/'.$s['id'];
    $query = '?'.parse_url($snapshot, PHP_URL_QUERY);
    $this->get('/panel/scoreboard/sessions/'.$s['id'].$query)->assertRedirect(route('panel.login'));
    $this->getJson($private.$query)->assertUnauthorized();
    $this->getJson($private.'/commands/'.$command['command_id'].$query)->assertUnauthorized();
    $this->postJson($private.'/commands'.$query, $command)->assertUnauthorized();
    $this->postJson($private.'/finish'.$query, [])->assertUnauthorized();
    $this->postJson('/api/v1/scoreboard/sessions'.$query, [])->assertUnauthorized();
    $this->postJson($snapshot, $command)->assertMethodNotAllowed();
    expect(ScoreboardSession::findOrFail($s['id'])->state['home']['score'])->toBe(0);
    expect(ScoreboardCommand::where('session_id', $s['id'])->count())->toBe(0);
});

test('display polls follow live score clock expiry and finished state without exposing private history', function (): void {
    $this->freezeTime();
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $private = '/api/v1/scoreboard/sessions/'.$s['id'];
    $snapshot = URL::signedRoute('scoreboard.sessions.display-snapshot', ['session' => $s['id']]);
    $s = $this->postJson($private.'/commands', scoreboardCommand($s, 'score.adjust', ['team' => 'home', 'delta' => 2]))->assertOk()->json('data.snapshot');
    $s = $this->postJson($private.'/commands', scoreboardCommand($s, 'game_clock.set', ['remaining_ms' => 2000]))->assertOk()->json('data.snapshot');
    $s = $this->postJson($private.'/commands', scoreboardCommand($s, 'game_clock.start'))->assertOk()->json('data.snapshot');
    auth()->forgetGuards();
    $this->app['session']->flush();
    $this->travel(1)->seconds();
    $this->getJson($snapshot)->assertOk()->assertJsonPath('data.state.home.score', 2)
        ->assertJsonPath('data.state.game_clock.remaining_ms', 1000)->assertJsonPath('data.state.game_clock.running', true);
    $this->travel(2)->seconds();
    $this->getJson($snapshot)->assertOk()->assertJsonPath('data.state.game_clock.remaining_ms', 0)
        ->assertJsonPath('data.state.game_clock.running', false);
    $this->actingAs($this->owner)->withSession(['current_community_id' => $this->community->id]);
    $s = $this->getJson($private)->assertOk()->json('data');
    $this->postJson($private.'/finish', ['panel_community_id' => $this->community->id, 'command_id' => (string) Str::uuid(),
        'expected_revision' => $s['state']['revision'], 'record_result' => true, 'home_name' => 'A', 'away_name' => 'B',
        'kind' => 'practice', 'location' => 'Lokasi privat'])->assertOk();
    auth()->forgetGuards();
    $this->app['session']->flush();
    $this->travel(2)->days();
    $this->getJson($snapshot)->assertOk()->assertJsonPath('data.status', 'finished')->assertJsonPath('data.state.home.score', 2)
        ->assertJsonMissingPath('data.result')->assertJsonMissingPath('data.location')
        ->assertJsonMissingPath('data.state.home.community_id')->assertJsonMissingPath('data.state.authority_epoch');
});

test('catalog lists master sports independently of community profile and hides inactive master', function (): void {
    $catalog = app(ScoreboardSports::class);
    $futsal = SportCategory::firstOrCreate(['slug' => 'futsal'], ['name' => 'Futsal', 'is_active' => true]);
    expect($catalog->supports($this->sport->id))->toBeTrue()->and($catalog->supports($futsal->id))->toBeFalse();
    $this->sport->update(['is_active' => false]);
    expect($catalog->supports($this->sport->id))->toBeFalse()->and(collect($catalog->all())->pluck('id')->contains($this->sport->id))->toBeFalse();
    ($this->createQuick)()->assertUnprocessable();
});

test('scoreboard renders all console and display modes without private deadlines', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $base = '/panel/scoreboard/sessions/'.$s['id'];
    $displays = collect(['main', 'shot-left', 'shot-right', 'demo'])->map(fn ($mode) => URL::signedRoute('panel.scoreboard.display', ['session' => $s['id'], 'viewMode' => $mode]));
    foreach ([$base, $base.'?console=shot', ...$displays] as $url) {
        $this->get($url)->assertOk()->assertSee('initScoreboardPage')->assertDontSee('deadline_ms')->assertDontSee('initialSession');
    }
    $this->get($base.'/display/unknown')->assertNotFound();
    $this->get($base.'?console=shot')->assertDontSee('data-finish=', false)->assertDontSee('data-scoreboard-cmd="score.adjust"', false);
    $this->get($displays->last())->assertDontSee('data-scoreboard-cmd=', false)->assertSee('data-readonly="true"', false);
    $this->get('/panel/scoreboard?sport_category_id=')->assertOk();
});

test('focus console keeps protected controls and optional tools without panel chrome', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $base = '/panel/scoreboard/sessions/'.$s['id'];
    $this->get($base)->assertOk()->assertSee('Mode Fokus HP');
    $html = $this->get($base.'?focus=1')->assertOk()
        ->assertSee('Mode Fokus')->assertSee('Lainnya')->assertSee('Home · Kiri')->assertSee('Away · Kanan')
        ->assertSee('id="sb-control-fields" disabled', false)
        ->assertSee('id="sb-review"', false)->assertSee('data-scoreboard-cmd="game_clock.stop"', false)
        ->assertDontSee('navbar-vertical', false)->getContent();
    expect($html)->not->toContain('data-reset="team_fouls.reset"');
    expect($html)->toContain('class="clock-reset" data-focus-open="sb-time"');
    expect($html)->toContain('data-period-step="1"', 'id="sb-duration-form"', 'id="sb-period-history"', 'data-time-reset');
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $node) {
        expect($ids)->not->toContain($node->getAttribute('id'));
        $ids[] = $node->getAttribute('id');
    }
    expect($xpath->query('//fieldset[@id="sb-control-fields"]//*[@data-scoreboard-cmd="score.adjust"]')->length)->toBe(8);
    expect($xpath->query('//dialog[@id="sb-reset"]//*[@data-reset="session.reset"]')->length)->toBe(1);
    expect($xpath->query('//fieldset[@id="sb-control-fields"]//*[@id="btnEnableAudio" or @id="btnToggleFullscreen" or @id="sb-review"]')->length)->toBe(0);
    expect($xpath->query('//section[contains(@class,"team")]//*[@data-scoreboard-cmd="score.adjust"]')->length)->toBe(8);
    expect($xpath->query('//section[contains(@class,"team")]//*[@data-scoreboard-cmd="team_fouls.adjust"]')->length)->toBe(4);
    expect($xpath->query('//section[contains(@class,"clocks")]//*[@data-scoreboard-cmd="timeout_clock.start"]')->length)->toBe(2);
    expect($xpath->query('//section[contains(@class,"clocks")]//*[@data-scoreboard-cmd="possession.set"]')->length)->toBe(3);
    foreach (['sb-time', 'sb-period-dialog', 'sb-shot', 'sb-reset', 'sb-more'] as $id) {
        expect($xpath->query('//dialog[@id="'.$id.'"]')->length)->toBe(1);
    }
    expect($xpath->query('//form[@id="sb-time-form"]//input[@name="minutes"]')->length)->toBe(1);
    $this->get($base.'?focus=1&console=shot')->assertOk()->assertDontSee('data-scoreboard-cmd="score.adjust"', false);
    auth()->forgetGuards();
    $this->flushSession();
    $this->get($base.'?focus=1')->assertRedirect();
});

test('console and main display keep Home left Away right and label the same scoreboard data', function (): void {
    $s = ($this->createQuick)()->assertCreated()->json('data');
    $urls = ['/panel/scoreboard/sessions/'.$s['id'],
        URL::signedRoute('panel.scoreboard.display', ['session' => $s['id'], 'viewMode' => 'main']),
        URL::signedRoute('panel.scoreboard.display', ['session' => $s['id'], 'viewMode' => 'demo'])];
    foreach ($urls as $url) {
        $response = $this->get($url)->assertOk()->assertSee('Home · Kiri')->assertSee('Away · Kanan')
            ->assertSee('Timeout terpakai', false)->assertSee('Timeout countdown', false)->assertSee('Possession', false);
        $html = $response->getContent();
        expect(strpos($html, 'Home · Kiri'))->toBeLessThan(strpos($html, 'Away · Kanan'));
    }
});

test('tools navigation preserves link tree policy and scoreboard ignores single sport profile', function (): void {
    $html = $this->get('/panel/profile')->assertOk()->getContent();
    $start = strpos($html, 'aria-label="Menu panel"');
    expect($start)->not->toBeFalse();
    $sidebar = substr($html, $start, strpos($html, '</nav>', $start) - $start);
    expect(substr_count($sidebar, 'href="'.route('panel.community-links.index').'"'))->toBe(1);
    expect($sidebar)->toContain('>Alat</div>')->toContain('href="'.route('panel.scoreboard.index').'"');
    expect(strpos($sidebar, '>Alat</div>'))->toBeLessThan(strpos($sidebar, 'href="'.route('panel.community-links.index').'"'));
    CommunityOfficial::where('user_id', $this->owner->id)->update(['ended_at' => today()]);
    request()->attributes->set('panel_active_community', app(CommunityLookup::class)->find($this->community->id));
    $sidebar = view('layouts.partials.panel-sidebar', ['activeCommunity' => request()->attributes->get('panel_active_community')])->render();
    expect($sidebar)->not->toContain('href="'.route('panel.scoreboard.index').'"')->not->toContain('href="'.route('panel.community-links.index').'"');
});
