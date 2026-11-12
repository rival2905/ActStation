<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\Scoreboard\Models\ScoreboardSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    expect(config('database.connections.mysql.database'))->toBe('db_actstation_test');
    Http::preventStrayRequests();
    $this->sport = SportCategory::firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
});

function publicScoreboardCommand(array $s, string $type, array $payload = []): array
{
    return ['command_id' => (string) Str::uuid(), 'session_id' => $s['id'], 'schema_version' => 'basketball.v1',
        'authority_epoch' => $s['state']['authority_epoch'], 'expected_revision' => $s['state']['revision'],
        'expires_at' => now()->addSeconds(4)->toISOString(), 'type' => $type, 'payload' => $payload];
}

test('public pages render personal tools in both console modes and claim without community links', function (): void {
    $this->get('/scoreboard')->assertOk()->assertSee('2 kali Start')->assertSee('scoreboard-create', false);
    $s = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated()->json('data');
    foreach ([0, 1] as $focus) {
        $this->get('/scoreboard/sessions/'.$s['id'].'?focus='.$focus)->assertOk()->assertSee('data-personal="true"', false)
            ->assertSee('Daftar Akun')->assertSee('Sudah punya akun? Masuk')->assertDontSee('sb-shot-share-form', false)
            ->assertDontSee('/display/main', false)->assertDontSee('data-finish="record"', false);
    }
    $this->actingAs(User::factory()->create(['is_active' => true]));
    $this->get('/scoreboard/sessions/'.$s['id'])->assertOk()->assertSee('Lanjutkan dengan akun ini');
});

test('trial quota survives period changes and does not grant community control', function (): void {
    $this->freezeTime();
    $s = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated()->json('data');
    $base = '/api/v1/scoreboard/personal/sessions/'.$s['id'];
    for ($i = 0; $i < 2; $i++) {
        $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.start'))->assertOk()->json('data.snapshot');
        $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.stop'))->assertOk()->json('data.snapshot');
    }
    $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'period.set', ['type' => 'regulation', 'number' => 2]))->assertOk()->assertJsonPath('data.snapshot.trial.starts_used', 2)->json('data.snapshot');
    $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'period.set', ['type' => 'regulation', 'number' => 1]))->assertOk()->assertJsonPath('data.snapshot.trial.starts_used', 2)->json('data.snapshot');
    $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'timeout_clock.start', ['duration_ms' => 30000]))->assertOk()->assertJsonPath('data.snapshot.state.game_clock.running', false)->json('data.snapshot');
    $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'timeout_clock.stop'))->assertOk()->json('data.snapshot');
    $this->postJson($base.'/commands', publicScoreboardCommand($s, 'shot_clock.start'))->assertOk()->assertJsonPath('data.status', 'rejected');
    $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.start'))->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.snapshot.trial.starts_used', 2);
    $this->postJson('/api/v1/scoreboard/sessions/'.$s['id'].'/commands', publicScoreboardCommand($s, 'game_clock.start'))->assertUnauthorized();
    $this->postJson('/api/v1/scoreboard/sessions/'.$s['id'].'/shot-access', ['panel_community_id' => 1, 'hours' => 2])->assertUnauthorized();
});

test('landing exposes public scoreboard without a login gate', function (): void {
    $this->get('/')->assertOk()->assertSee('href="'.route('scoreboard.public.index').'"', false)->assertSee('Coba Scoreboard');
});

test('public mutations retain CSRF protection', function (): void {
    $environment = app()->environment();
    app()->instance('env', 'local');
    try {
        $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertStatus(419);
        $token = Str::random(40);
        $this->withSession(['_token' => $token])->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated();
    } finally {
        app()->instance('env', $environment);
    }
});

test('trial commands reject stale invalid and expired starts without consuming quota', function (): void {
    $this->freezeTime();
    $s = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated()->json('data');
    $base = '/api/v1/scoreboard/personal/sessions/'.$s['id'];
    $bad = publicScoreboardCommand($s, 'game_clock.start', ['extra' => 1]);
    $this->postJson($base.'/commands', $bad)->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.snapshot.trial.starts_used', 0);
    $bad = publicScoreboardCommand($s, 'game_clock.start');
    $bad['expected_revision'] = 100;
    $this->postJson($base.'/commands', $bad)->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.snapshot.trial.starts_used', 0);
    $bad = publicScoreboardCommand($s, 'game_clock.start');
    $bad['expires_at'] = now()->subSecond()->toISOString();
    $this->postJson($base.'/commands', $bad)->assertOk()->assertJsonPath('data.status', 'expired')->assertJsonPath('data.snapshot.trial.starts_used', 0);
    $valid = publicScoreboardCommand($s, 'game_clock.start');
    $ack = $this->postJson($base.'/commands', $valid)->assertOk()->assertJsonPath('data.status', 'applied')->json('data');
    $this->getJson($base.'/commands/'.$valid['command_id'])->assertExactJson(['data' => $ack]);
    $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.start'))->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.snapshot.trial.starts_used', 1);
    $valid['payload'] = ['extra' => 1];
    $this->postJson($base.'/commands', $valid)->assertStatus(409);
});

test('trial ownership is browser bound and personal accounts cannot access other sessions', function (): void {
    $s = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated()->json('data');
    $base = '/api/v1/scoreboard/personal/sessions/'.$s['id'];
    $token = session('scoreboard_trial_token');
    $this->flushSession();
    $this->getJson($base)->assertForbidden();
    $this->get('/scoreboard/sessions/'.$s['id'].'/auth/register')->assertForbidden();
    $this->postJson($base.'/commands', publicScoreboardCommand($s, 'score.adjust', ['team' => 'home', 'delta' => 3]))->assertForbidden();
    $owner = User::factory()->create(['is_active' => true]);
    $this->actingAs($owner)->postJson($base.'/claim')->assertForbidden();
    $this->withSession(['scoreboard_trial_token' => $token, 'scoreboard_trial_id' => $s['id']]);
    $this->post($base.'/claim')->assertRedirect();
    $this->post($base.'/claim')->assertRedirect();
    $this->getJson($base)->assertOk()->assertJsonPath('data.trial.limited', false);
    $this->actingAs(User::factory()->create(['is_active' => true]));
    $this->getJson($base)->assertForbidden();
    $this->postJson($base.'/claim')->assertForbidden();
    $personal = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated()->assertJsonPath('data.trial.limited', false)->json('data');
    expect($personal['community_id'])->toBeNull();
    $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id, 'state' => ['home' => ['score' => 999]]])->assertUnprocessable();
    $this->actingAs(User::factory()->create(['is_active' => false]));
    $this->getJson('/api/v1/scoreboard/personal/sessions/'.$personal['id'])->assertForbidden();
});

test('login returns to trial and registered owner can exceed guest start limit', function (): void {
    $this->freezeTime();
    $s = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated()->json('data');
    $base = '/api/v1/scoreboard/personal/sessions/'.$s['id'];
    $user = User::factory()->create(['is_active' => true, 'password' => bcrypt('TestPassword123!')]);
    $this->get('/scoreboard/sessions/'.$s['id'].'/auth/login')->assertRedirect(route('panel.login'));
    $this->post('/panel/login', ['email' => $user->email, 'password' => 'TestPassword123!'])->assertRedirect(route('scoreboard.public.show', $s['id']));
    $this->post($base.'/claim')->assertRedirect();
    foreach ([1, 2, 3] as $start) {
        $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.start'))->assertOk()->assertJsonPath('data.status', 'applied')->json('data.snapshot');
        $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.stop'))->assertOk()->json('data.snapshot');
    }
    $this->getJson($base)->assertOk()->assertJsonPath('data.trial.limited', false);
});

test('personal claim returns a resource to JSON callers without a false start limit', function (): void {
    $s = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated()->json('data');
    $this->actingAs(User::factory()->create(['is_active' => true]));
    $this->postJson('/api/v1/scoreboard/personal/sessions/'.$s['id'].'/claim')->assertOk()
        ->assertJsonPath('data.id', $s['id'])->assertJsonPath('data.trial.limited', false)
        ->assertJsonPath('data.trial.starts_remaining', null);
});

test('registration ignores forged scoreboard return destinations', function (): void {
    Event::fake([Registered::class]);
    $this->withSession(['scoreboard_registration_return' => 'https://example.test/scoreboard',
        'scoreboard_trial_id' => 'https://example.test/scoreboard', 'scoreboard_trial_token' => 'invalid']);
    $this->post('/register', ['name' => 'Trial Tester', 'email' => Str::uuid().'@example.test',
        'phone_number' => '0812'.random_int(10000000, 99999999),
        'password' => 'TestPassword123!', 'password_confirmation' => 'TestPassword123!'])
        ->assertRedirect(route('events.index'));
});

test('registration returns to owned trial and claim preserves state without community', function (): void {
    Event::fake([Registered::class]);
    $s = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertCreated()->json('data');
    $base = '/api/v1/scoreboard/personal/sessions/'.$s['id'];
    $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'score.adjust', ['team' => 'home', 'delta' => 2]))->assertOk()->json('data.snapshot');
    $before = ScoreboardSession::findOrFail($s['id'])->state;
    $this->get('/scoreboard/sessions/'.$s['id'].'/auth/register')->assertRedirect(route('register'));
    $email = Str::uuid().'@example.test';
    $this->post('/register', ['name' => 'Trial Tester', 'email' => $email, 'phone_number' => '0812'.random_int(10000000, 99999999),
        'password' => 'TestPassword123!', 'password_confirmation' => 'TestPassword123!'])->assertRedirect(route('scoreboard.public.show', $s['id']));
    $this->post($base.'/claim')->assertRedirect(route('scoreboard.public.show', $s['id']));
    $model = ScoreboardSession::findOrFail($s['id']);
    expect($model->state)->toBe($before)->and($model->community_id)->toBeNull()->and((int) $model->created_by)->toBe((int) auth()->id());
    $this->getJson($base)->assertOk()->assertJsonPath('data.trial.limited', false)->assertJsonPath('data.state.home.score', 2);
    $this->postJson($base.'/finish', ['command_id' => (string) Str::uuid(), 'expected_revision' => $s['state']['revision'], 'record_result' => true])->assertUnprocessable();
    $this->postJson($base.'/finish', ['command_id' => (string) Str::uuid(), 'expected_revision' => $s['state']['revision'], 'record_result' => false])->assertOk()->assertJsonPath('data.status', 'finished')->assertJsonPath('data.result', null);
});

test('public trial permits two successful starts and preserves quota across retries resets and reloads', function (): void {
    $this->freezeTime();
    $s = $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id, 'home_name' => 'Merah', 'away_name' => 'Biru'])->assertCreated()->json('data');
    $base = '/api/v1/scoreboard/personal/sessions/'.$s['id'];
    expect(ScoreboardSession::findOrFail($s['id'])->community_id)->toBeNull();
    $this->getJson($base)->assertOk()->assertJsonPath('data.trial.starts_remaining', 2);
    foreach ([1, 2] as $count) {
        $command = publicScoreboardCommand($s, 'game_clock.start');
        $ack = $this->postJson($base.'/commands', $command)->assertOk()->assertJsonPath('data.status', 'applied')->assertJsonPath('data.snapshot.trial.starts_used', $count);
        $this->postJson($base.'/commands', $command)->assertExactJson($ack->json());
        $s = $ack->json('data.snapshot');
        $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.start'))->assertOk()->assertJsonPath('data.snapshot.trial.starts_used', $count)->json('data.snapshot');
        $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.stop'))->assertOk()->assertJsonPath('data.status', 'applied')->json('data.snapshot');
    }
    $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'score.adjust', ['team' => 'home', 'delta' => 3]))->assertOk()->json('data.snapshot');
    $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.reset'))->assertOk()->assertJsonPath('data.snapshot.trial.starts_used', 2)->json('data.snapshot');
    $this->postJson($base.'/commands', publicScoreboardCommand($s, 'game_clock.start'))->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.reason', 'Percobaan selesai. Daftar akun untuk melanjutkan scoreboard ini.')->assertJsonPath('data.snapshot.state.home.score', 3)->assertJsonPath('data.snapshot.state.game_clock.running', false);
    $s = $this->postJson($base.'/commands', publicScoreboardCommand($s, 'session.reset', ['confirmed' => true, 'reason' => 'Tes']))->assertOk()->assertJsonPath('data.snapshot.trial.starts_used', 2)->json('data.snapshot');
    $this->getJson($base)->assertOk()->assertJsonPath('data.trial.starts_used', 2)->assertJsonMissingPath('data.state._trial');
    $this->postJson('/api/v1/scoreboard/personal/sessions', ['sport_category_id' => $this->sport->id])->assertOk()->assertJsonPath('data.id', $s['id']);
});
