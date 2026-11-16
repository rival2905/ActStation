<?php

declare(strict_types=1);

use App\Models\AgentOfficePrincipal;
use App\Services\AgentOfficeSecurity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('operator rejects stale publication and preserves encrypted effective version', function (): void {
    $f = gatewayFixture();
    try {
        $stale = $f['snapshot'];
        $new = $stale;
        $new['version']++;
        $new['keys']['fixture-key']['enabled'] = false;
        app(AgentOfficeSecurity::class)->publish($new);
        expect(fn () => app(AgentOfficeSecurity::class)->publish($stale))->toThrow(RuntimeException::class);
        expect(app(AgentOfficeSecurity::class)->snapshot()['keys']['fixture-key']['enabled'])->toBeFalse();
    } finally {
        gatewayCleanup($f);
    }
});

test('operator disable preserves key bindings and can inspect disabled registry for recovery', function (): void {
    $f = gatewayFixture();
    try {
        app(AgentOfficeSecurity::class)->disable();
        $snapshot = app(AgentOfficeSecurity::class)->snapshot(false);
        expect($snapshot['enabled'])->toBeTrue()->and($snapshot['keys'])->toHaveKey('fixture-key');
        expect(fn () => app(AgentOfficeSecurity::class)->snapshot())->toThrow(RuntimeException::class, 'emergency_disabled');
        $this->artisan('agent-office:provision', ['--name' => 'Recovery fixture', '--cidr' => ['::1/128'], '--output' => $f['dir'].'/recovery.json'])->assertSuccessful();
        expect(fn () => app(AgentOfficeSecurity::class)->snapshot())->toThrow(RuntimeException::class, 'emergency_disabled');
        app(AgentOfficeSecurity::class)->reenable(2, 123);
        expect(app(AgentOfficeSecurity::class)->snapshot()['keys'])->toHaveCount(2);
    } finally {
        gatewayCleanup($f);
    }
});

test('registry refuses application encryption key reuse', function (): void {
    $f = gatewayFixture();
    try {
        config(['app.key' => 'base64:'.config('agent_office.master_key')]);
        expect(fn () => app(AgentOfficeSecurity::class)->snapshot())->toThrow(RuntimeException::class);
    } finally {
        gatewayCleanup($f);
    }
});

test('invalid provisioning never queries database or writes output', function (): void {
    $f = gatewayFixture();
    try {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->artisan('agent-office:provision', ['--name' => 'Invalid CIDR', '--cidr' => ['0.0.0.0/0'], '--output' => $f['dir'].'/invalid.json'])->assertFailed();
        $this->artisan('agent-office:provision', ['--name' => 'Public output', '--cidr' => ['127.0.0.1/32'], '--output' => public_path('gateway-secret.json')])->assertFailed();
        expect(DB::getQueryLog())->toBe([])->and(is_file($f['dir'].'/invalid.json'))->toBeFalse();
        DB::disableQueryLog();
        expect(AgentOfficePrincipal::whereKey($f['principal']->id)->exists())->toBeTrue();
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('provisioning defaults to health read and rejects unknown or wildcard abilities before database', function (): void {
    $f = gatewayFixture();
    try {
        $this->artisan('agent-office:provision', ['--name' => 'Health default', '--cidr' => ['127.0.0.1/32'], '--output' => $f['dir'].'/health.json'])->assertSuccessful();
        $health = json_decode(file_get_contents($f['dir'].'/health.json'), true, 8, JSON_THROW_ON_ERROR);
        expect($health['abilities'])->toBe(['agent:health-read'])
            ->and(AgentOfficePrincipal::findOrFail($health['principal_id'])->tokens()->sole()->abilities)->toBe(['agent:health-read']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (['*', 'agent:admin', 'agent:social-collector,agent:social-sender', ' agent:social-sender', ''] as $i => $ability) {
            $this->artisan('agent-office:provision', ['--name' => 'Bad ability', '--ability' => $ability, '--cidr' => ['127.0.0.1/32'], '--output' => $f['dir']."/bad-$i.json"])->assertFailed();
            expect(is_file($f['dir']."/bad-$i.json"))->toBeFalse();
        }
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('provisioned relay principal holds exactly one social ability and passes the real relay gateway', function (): void {
    $f = gatewayFixture();
    try {
        config(['agent_office.social_relay_enabled' => true]);
        $this->artisan('agent-office:provision', ['--name' => 'n8n collector', '--ability' => 'agent:social-collector', '--cidr' => ['127.0.0.1/32'], '--output' => $f['dir'].'/collector.json'])->assertSuccessful();
        $c = json_decode(file_get_contents($f['dir'].'/collector.json'), true, 8, JSON_THROW_ON_ERROR);
        expect($c['abilities'])->toBe(['agent:social-collector'])
            ->and(AgentOfficePrincipal::findOrFail($c['principal_id'])->tokens()->sole()->abilities)->toBe(['agent:social-collector'])
            ->and(substr(sprintf('%o', fileperms($f['dir'].'/collector.json')), -4))->toBeIn(['0600', '0666']);
        $call = function (string $action, array $payload) use ($c) {
            $target = '/api/v1/internal/agent-office/social-relay/'.$action;
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $ts = (string) time();
            $nonce = bin2hex(random_bytes(32));
            $idem = (string) Str::uuid();
            $sig = app(AgentOfficeSecurity::class)->signature('POST', $target, $ts, $nonce, $c['key_id'], $c['token'], $body, $idem, base64_decode($c['secret'], true));

            return $this->call('POST', $target, [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$c['token'], 'HTTP_X_AGENT_KEY_ID' => $c['key_id'], 'HTTP_X_AGENT_TIMESTAMP' => $ts, 'HTTP_X_AGENT_NONCE' => $nonce,
                'HTTP_IDEMPOTENCY_KEY' => $idem, 'HTTP_X_AGENT_SIGNATURE' => $sig], $body);
        };
        $comment = ['account_ref' => 'actstationcontinent', 'platform' => 'instagram', 'media' => ['id' => '123', 'permalink' => 'https://www.instagram.com/p/synthetic/', 'published_at' => now()->subHour()->toIso8601String()],
            'comments' => [['id' => '456', 'text' => 'synthetic', 'timestamp' => now()->subMinute()->toIso8601String()]], 'page' => 1];
        $call('publish', ['channel' => 'comments', 'account_ref' => 'actstationcontinent', 'delivery_id' => (string) Str::uuid(), 'payload' => $comment])->assertCreated();
        // Role matrix still applies: a collector cannot pull dispatch.
        $call('pull', ['channel' => 'dispatch', 'account_ref' => 'actstationcontinent'])->assertForbidden();
    } finally {
        gatewayCleanup($f);
    }
});

test('default audit lives in the private registry directory, never in shared logs', function (): void {
    $config = require base_path('config/agent_office.php');
    expect(dirname($config['audit_path']))->toBe(dirname($config['registry_path']))
        ->and(dirname($config['state_path']))->toBe(dirname($config['registry_path']))
        ->and(str_replace('\\', '/', $config['audit_path']))->not->toContain('storage/logs');
});
