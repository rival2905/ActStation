<?php

declare(strict_types=1);

use App\Services\AgentOfficeSecurity;
use Illuminate\Support\Facades\DB;

test('expiry and human session cannot bypass bot authentication', function (): void {
    $f = gatewayFixture();
    try {
        $h = gatewayHeaders($f);
        unset($h['Authorization']);
        $this->get('/api/v1/internal/agent-office/system-health', $h)->assertForbidden();
        $f['snapshot']['allowlist'][0]['expires_at'] = time() - 1;
        $f['snapshot']['version']++;
        app(AgentOfficeSecurity::class)->publish($f['snapshot']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertForbidden();
        expect(DB::getQueryLog())->toBe([]);
        DB::disableQueryLog();
        $f['snapshot']['allowlist'][0]['expires_at'] = null;
        $f['snapshot']['keys']['fixture-key']['expires_at'] = time() - 1;
        $f['snapshot']['version']++;
        app(AgentOfficeSecurity::class)->publish($f['snapshot']);
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertForbidden();
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('failed registry validation preserves effective version and ciphertext', function (): void {
    $f = gatewayFixture();
    try {
        $before = hash_file('sha256', $f['dir'].'/registry.enc');
        $f['snapshot']['allowlist'][0]['cidr'] = '0.0.0.0/0';
        expect(fn () => app(AgentOfficeSecurity::class)->publish($f['snapshot']))->toThrow(RuntimeException::class);
        expect(hash_file('sha256', $f['dir'].'/registry.enc'))->toBe($before);
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertOk();
        expect(file_get_contents($f['dir'].'/registry.enc'))->not->toContain(base64_encode($f['secret']));
    } finally {
        gatewayCleanup($f);
    }
});

test('ambiguous length and malformed header bytes are denied before database', function (): void {
    $f = gatewayFixture();
    try {
        foreach ([['Content-Length' => ['0', '1']], ['X-Untrusted' => "\xFF"]] as $invalid) {
            $headers = array_merge(gatewayHeaders($f), $invalid);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get('/api/v1/internal/agent-office/system-health', $headers)
                ->assertForbidden()->assertJsonPath('meta.code', 'gateway_forbidden');
            expect(DB::getQueryLog())->toBe([]);
            DB::disableQueryLog();
        }
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('gateway rejects duplicate and encoded query ambiguity with no business endpoints', function (): void {
    $f = gatewayFixture();
    try {
        foreach (['?x=1&x=2', '?x=%2F', '?x[]=1'] as $query) {
            $path = '/api/v1/internal/agent-office/system-health'.$query;
            $this->get($path, gatewayHeaders($f, target: $path))->assertStatus(422);
        }
        $this->get('/api/v1/internal/agent-office/metrics', gatewayHeaders($f))->assertNotFound();
        $this->postJson('/api/v1/internal/agent-office/admin-reviews/organizers/1/decision', [])->assertNotFound();
    } finally {
        gatewayCleanup($f);
    }
});
