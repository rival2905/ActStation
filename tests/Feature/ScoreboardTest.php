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
        $response->assertJsonPath('data.result.away.score', 3)->assertJsonPath('data.result.visibility', 'private')->assertJsonPath('data.result.status', 'recorded');
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
    foreach ([$base, $base.'?console=shot', $base.'/display/main', $base.'/display/shot-left', $base.'/display/shot-right', $base.'/display/demo'] as $url) {
        $this->get($url)->assertOk()->assertSee('initScoreboardPage')->assertDontSee('deadline_ms')->assertDontSee('initialSession');
    }
    $this->get($base.'/display/unknown')->assertNotFound();
    $this->get($base.'?console=shot')->assertDontSee('data-finish=', false)->assertDontSee('data-scoreboard-cmd="score.adjust"', false);
    $this->get($base.'/display/demo')->assertDontSee('data-scoreboard-cmd=', false)->assertSee('data-readonly="true"', false);
    $this->get('/panel/scoreboard?sport_category_id=')->assertOk();
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
