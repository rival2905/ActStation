<?php

declare(strict_types=1);

use App\Models\AgentOfficePrincipal;
use App\Services\AgentOfficeSecurity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function gatewayFixture(): array
{
    $c = config('database.connections.mysql');
    if (! app()->environment('testing') || $c['database'] !== 'db_actstation_test' || ! in_array($c['host'], ['127.0.0.1', 'localhost'], true) || ! empty($c['url']) || ! empty($c['read']) || ! empty($c['write']) || ! empty($c['unix_socket']) || DB::selectOne('SELECT DATABASE() AS db')->db !== 'db_actstation_test') {
        throw new RuntimeException('test_database_denied');
    }
    DB::beginTransaction();
    Http::preventStrayRequests();
    $dir = str_replace('\\', '/', getenv('TMPDIR')).'/gateway-'.bin2hex(random_bytes(8));
    mkdir($dir, 0700, true);
    config(['agent_office.enabled' => true, 'agent_office.allow_loopback_http' => true, 'agent_office.master_key' => base64_encode(random_bytes(32)), 'agent_office.registry_path' => $dir.'/registry.enc', 'agent_office.state_path' => $dir.'/state.json', 'agent_office.audit_path' => $dir.'/audit.jsonl']);
    $principal = AgentOfficePrincipal::create(['name' => 'Synthetic gateway', 'enabled' => true]);
    $token = $principal->createToken('fixture', ['agent:health-read'], now()->addHour())->plainTextToken;
    $secret = random_bytes(32);
    $snapshot = ['version' => 1, 'enabled' => true, 'expires_at' => time() + 3600, 'allowlist' => [['cidr' => '127.0.0.1/32', 'expires_at' => null]], 'keys' => ['fixture-key' => ['secret' => base64_encode($secret), 'principal_id' => $principal->id, 'expires_at' => time() + 3600, 'enabled' => true]]];
    app(AgentOfficeSecurity::class)->publish($snapshot);

    return compact('dir', 'principal', 'token', 'secret', 'snapshot');
}

function gatewayHeaders(array $fixture, ?string $nonce = null, string $target = '/api/v1/internal/agent-office/system-health'): array
{
    $timestamp = (string) time();
    $nonce ??= bin2hex(random_bytes(32));
    $canonical = implode("\n", ['v1', 'GET', $target, $timestamp, $nonce, 'fixture-key', hash('sha256', $fixture['token']), hash('sha256', ''), '']);

    return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$fixture['token'], 'X-Agent-Key-Id' => 'fixture-key', 'X-Agent-Timestamp' => $timestamp, 'X-Agent-Nonce' => $nonce, 'X-Agent-Signature' => hash_hmac('sha256', $canonical, $fixture['secret'])];
}

function gatewayCleanup(array $fixture): void
{
    DB::rollBack();
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture['dir'], FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($fixture['dir']);
}

test('gateway is disabled and rejects before any database query', function (): void {
    Http::preventStrayRequests();
    config(['agent_office.enabled' => false]);
    DB::listen(function (): never {
        throw new RuntimeException('gateway_queried_database');
    });
    $this->get('/api/v1/internal/agent-office/system-health')
        ->assertForbidden()
        ->assertJsonPath('meta.code', 'gateway_forbidden')
        ->assertJsonStructure(['message', 'errors', 'meta' => ['request_id']]);
});

test('signed persistent bot health measures database without external requests', function (): void {
    $f = gatewayFixture();
    try {
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))
            ->assertOk()->assertJsonPath('data.status', 'healthy')->assertJsonPath('data.database', 'connected')
            ->assertJsonPath('data.n8n_status', 'not_checked')->assertJsonPath('meta.contract_version', 'agent-office.v1')
            ->assertJsonStructure(['data' => ['checked_at', 'latency_ms'], 'meta' => ['request_id']]);
        Http::assertNothingSent();
    } finally {
        gatewayCleanup($f);
    }
});

test('gateway rejects invalid signature before database and does not consume nonce', function (): void {
    $f = gatewayFixture();
    try {
        $headers = gatewayHeaders($f);
        $bad = $headers;
        $bad['X-Agent-Signature'] = str_repeat('0', 64);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/api/v1/internal/agent-office/system-health', $bad)->assertForbidden();
        expect(DB::getQueryLog())->toBe([]);
        DB::disableQueryLog();
        $this->get('/api/v1/internal/agent-office/system-health', $headers)->assertOk();
        $this->get('/api/v1/internal/agent-office/system-health', $headers)->assertStatus(409)->assertJsonPath('meta.code', 'gateway_replay');
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('gateway does not trust spoofed forwarding and checks source before database', function (): void {
    $f = gatewayFixture();
    try {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/api/v1/internal/agent-office/system-health', array_merge(gatewayHeaders($f), ['X-Forwarded-For' => '127.0.0.1', 'Forwarded' => 'for=127.0.0.1;proto=https']))->assertForbidden();
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('gateway denies stale wrong ability wildcard disabled and expired tokens', function (): void {
    $f = gatewayFixture();
    try {
        foreach ([['agent:health'], ['*'], ['agent:metrics-read']] as $abilities) {
            $f['token'] = $f['principal']->createToken('denied', $abilities, now()->addHour())->plainTextToken;
            $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertForbidden();
        }
        $f['token'] = $f['principal']->createToken('expired', ['agent:health-read'], now()->subMinute())->plainTextToken;
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertUnauthorized();
        $f['token'] = $f['principal']->createToken('valid', ['agent:health-read'], now()->addHour())->plainTextToken;
        $f['principal']->update(['enabled' => false]);
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertForbidden();
    } finally {
        gatewayCleanup($f);
    }
});

test('registry revocation corrupt state and audit failures deny subsequent health', function (): void {
    $f = gatewayFixture();
    try {
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertOk();
        $f['snapshot']['keys']['fixture-key']['enabled'] = false;
        $f['snapshot']['version']++;
        app(AgentOfficeSecurity::class)->publish($f['snapshot']);
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertForbidden();
        file_put_contents($f['dir'].'/registry.enc', 'broken');
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertStatus(503);
    } finally {
        gatewayCleanup($f);
    }
});

test('quota binds principal across tokens and redacts secrets from audit', function (): void {
    $f = gatewayFixture();
    try {
        for ($i = 0; $i < 60; $i++) {
            $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertOk();
        }
        $second = $f['principal']->createToken('second', ['agent:health-read'], now()->addHour())->plainTextToken;
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders(array_merge($f, ['token' => $second])))
            ->assertStatus(429)->assertHeader('Retry-After');
        $audit = file_get_contents($f['dir'].'/audit.jsonl');
        expect($audit)->not->toContain($f['token'])->not->toContain($second)->not->toContain(base64_encode($f['secret']));
    } finally {
        gatewayCleanup($f);
    }
});

test('signed health rejects malformed input and unknown queries', function (): void {
    $f = gatewayFixture();
    try {
        foreach (['X-Agent-Timestamp' => (string) (time() - 61), 'X-Agent-Nonce' => 'invalid', 'Accept' => 'text/html', 'Authorization' => 'Bearer human-session', 'X-Agent-Key-Id' => 'unknown'] as $key => $value) {
            $h = gatewayHeaders($f);
            $h[$key] = $value;
            $this->get('/api/v1/internal/agent-office/system-health', $h)->assertForbidden();
        }
        $h = gatewayHeaders($f);
        $h['X-Agent-Key-Id'] = ['fixture-key', 'fixture-key'];
        $this->get('/api/v1/internal/agent-office/system-health', $h)->assertForbidden();
        $this->call('GET', '/api/v1/internal/agent-office/system-health', [], [], [], $this->transformHeadersToServerVars(gatewayHeaders($f)), 'body')->assertForbidden();
        $query = '/api/v1/internal/agent-office/system-health?unknown=1';
        $this->get($query, gatewayHeaders($f, target: $query))->assertStatus(422);
        $this->get($query, gatewayHeaders($f))->assertForbidden();
        $this->postJson('/api/v1/internal/agent-office/verify-team', [])->assertNotFound();
    } finally {
        gatewayCleanup($f);
    }
});

test('audit and shared state failure cannot return healthy', function (): void {
    $f = gatewayFixture();
    try {
        config(['agent_office.audit_path' => $f['dir'].'/missing/audit.jsonl']);
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertStatus(503);
        config(['agent_office.audit_path' => $f['dir'].'/audit.jsonl']);
        file_put_contents($f['dir'].'/state.json', 'malformed');
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertStatus(503);
    } finally {
        gatewayCleanup($f);
    }
});

test('signed token revocation and principal binding are fresh', function (): void {
    $f = gatewayFixture();
    try {
        $f['snapshot']['keys']['fixture-key']['principal_id'] = $f['principal']->id + 1;
        $f['snapshot']['version']++;
        app(AgentOfficeSecurity::class)->publish($f['snapshot']);
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertForbidden();
        $f['snapshot']['keys']['fixture-key']['principal_id'] = $f['principal']->id;
        $f['snapshot']['version']++;
        app(AgentOfficeSecurity::class)->publish($f['snapshot']);
        $f['principal']->tokens()->delete();
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertUnauthorized();
    } finally {
        gatewayCleanup($f);
    }
});

test('provision writes only private exclusive output and preserves older keys', function (): void {
    $f = gatewayFixture();
    try {
        $output = $f['dir'].'/operator.json';
        $this->artisan('agent-office:provision', ['--name' => 'Synthetic second bot', '--cidr' => ['::1/128'], '--output' => $output, '--days' => '1'])->assertSuccessful();
        expect(is_file($output))->toBeTrue();
        $snapshot = app(AgentOfficeSecurity::class)->snapshot();
        expect($snapshot['keys'])->toHaveCount(2)->toHaveKey('fixture-key');
        $count = AgentOfficePrincipal::count();
        $this->artisan('agent-office:provision', ['--name' => 'Duplicate output', '--cidr' => ['127.0.0.1/32'], '--output' => $output])->assertFailed();
        expect(AgentOfficePrincipal::count())->toBe($count);
        $this->artisan('agent-office:provision', ['--disable' => true])->assertSuccessful();
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertStatus(503);
    } finally {
        gatewayCleanup($f);
    }
});
