<?php

declare(strict_types=1);

use App\Modules\Report\Http\Controllers\AgentOfficeHealthController;
use App\Services\AgentOfficeSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('health dependency failure retains safe allowlisted degraded shape', function (): void {
    Http::preventStrayRequests();
    DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new RuntimeException('private-canary'));
    $request = Request::create('/');
    $request->attributes->set('gateway_request_id', 'fixture-request');
    $response = app(AgentOfficeHealthController::class)($request);
    expect($response->getStatusCode())->toBe(503);
    $body = $response->getData(true);
    expect($body['data']['status'])->toBe('degraded')->and($body['data']['database'])->toBe('unavailable')
        ->and($body['data']['n8n_status'])->toBe('not_checked')->and($body['meta']['request_id'])->toBe('fixture-request')
        ->and($response->getContent())->not->toContain('private-canary');
    Http::assertNothingSent();
});

test('cidr and signing contracts preserve raw byte scope', function (): void {
    $security = app(AgentOfficeSecurity::class);
    foreach (['127.0.0.1/32', '::1/128', '192.0.2.0/24', '2001:db8::/32'] as $cidr) {
        expect(AgentOfficeSecurity::validCidr($cidr))->toBeTrue();
    }
    foreach (['0.0.0.0/0', '::/0', '192.0.2.1/24', '127.0.0.1/033', 'localhost/32', '*/32'] as $cidr) {
        expect(AgentOfficeSecurity::validCidr($cidr))->toBeFalse();
    }
    expect($security->allowedIp('::1', ['allowlist' => [['cidr' => '::1/128', 'expires_at' => null]]]))->toBeTrue();
    expect($security->allowedIp('::1', ['allowlist' => [['cidr' => '::1/128', 'expires_at' => time() - 1]]]))->toBeFalse();
    $secret = str_repeat('s', 32);
    $base = ['GET', '/path?x=%C3%A9&z=1', '1792828800', str_repeat('a', 64), 'dummy-key', 'dummy-token', '', '', $secret];
    $canonical = implode("\n", ['v1', ...array_slice($base, 0, 5), hash('sha256', 'dummy-token'), hash('sha256', ''), '']);
    expect($security->signature(...$base))->toBe(hash_hmac('sha256', $canonical, $secret));
    foreach ([0 => 'POST', 1 => '/path?z=1&x=%C3%A9', 5 => 'changed-token', 6 => '{ "x": "é" }', 7 => 'dummy-idempotency'] as $index => $value) {
        $changed = $base;
        $changed[$index] = $value;
        expect($security->signature(...$changed))->not->toBe($security->signature(...$base));
    }
});

test('core verifier matches shared independently frozen Office signing vectors', function (): void {
    $vectors = json_decode(file_get_contents(dirname(__DIR__, 3).'/ActStationOffice/tests/fixtures/agent-office-signing-v1.json'), true, 32, JSON_THROW_ON_ERROR)['vectors'];
    foreach ($vectors as $v) {
        expect(app(AgentOfficeSecurity::class)->signature($v['method'], $v['target'], $v['timestamp'], $v['nonce'], $v['key_id'], $v['token'], $v['body'], $v['idempotency'], $v['secret']))->toBe($v['expected_signature']);
    }
});

test('assistant session routes remain separate from stateless machine gateway', function (): void {
    $this->getJson('/api/v1/assistant/history')->assertUnauthorized();
    $routes = app('router')->getRoutes();
    $assistant = $routes->match(Request::create('/api/v1/assistant/history'));
    $health = $routes->match(Request::create('/api/v1/internal/agent-office/system-health'));
    expect($assistant->gatherMiddleware())->toContain('web', 'auth:web')
        ->and($health->gatherMiddleware())->not->toContain('web', 'auth:web');
});
