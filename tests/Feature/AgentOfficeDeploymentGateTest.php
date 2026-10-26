<?php

declare(strict_types=1);

use App\Services\AgentOfficeSecurity;
use App\Services\AgentOfficeState;
use Illuminate\Support\Facades\DB;

test('break glass survives corrupt registry and absent master key without database or replay reset', function (): void {
    $f = gatewayFixture();
    try {
        $security = app(AgentOfficeSecurity::class);
        app(AgentOfficeState::class)->claim('preserved-replay', 300);
        $state = file_get_contents($f['dir'].'/state.json');
        file_put_contents($f['dir'].'/registry.enc', 'corrupt');
        config(['agent_office.master_key' => null]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->artisan('agent-office:provision', ['--disable' => true])->assertSuccessful();
        expect(DB::getQueryLog())->toBe([]);
        expect(file_get_contents($f['dir'].'/state.json'))->toBe($state);
        expect(is_file($f['dir'].'/registry.enc.disabled'))->toBeTrue();
        expect(file_get_contents($f['dir'].'/registry.enc.disabled'))->toContain('emergency_disabled');
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertStatus(503);
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('official proxy resolves client not proxy allowlist and rejects forged TLS before database', function (): void {
    $f = gatewayFixture();
    try {
        config(['agent_office.trusted_proxies' => ['127.0.0.1/32'], 'agent_office.allow_loopback_http' => false]);
        $this->get('/api/v1/internal/agent-office/system-health', array_merge(gatewayHeaders($f), ['X-Forwarded-For' => '127.0.0.1', 'X-Forwarded-Proto' => 'https']))->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/api/v1/internal/agent-office/system-health', array_merge(gatewayHeaders($f), ['X-Forwarded-For' => '203.0.113.2', 'X-Forwarded-Proto' => 'https']))->assertForbidden();
        expect(DB::getQueryLog())->toBe([]);
        config(['agent_office.trusted_proxies' => []]);
        $this->get('/api/v1/internal/agent-office/system-health', array_merge(gatewayHeaders($f), ['X-Forwarded-For' => '127.0.0.1', 'X-Forwarded-Proto' => 'https']))->assertForbidden();
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('allowlist publication compares expected version and reports effective readback', function (): void {
    $f = gatewayFixture();
    try {
        $security = app(AgentOfficeSecurity::class);
        $entries = [['id' => 'synthetic-entry', 'cidr' => '203.0.113.10/32', 'label' => 'Fixture', 'enabled' => true, 'expires_at' => time() + 60]];
        $result = $security->replaceAllowlist($entries, 1, 123);
        expect($result['status'])->toBe('applied')->and($result['effective_version'])->toBe(2);
        expect($security->snapshot()['allowlist'][0]['created_by'])->toBe(123);
        expect(fn () => $security->replaceAllowlist($entries, 1, 123))->toThrow(RuntimeException::class, 'registry_version_conflict');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertForbidden();
        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
        gatewayCleanup($f);
    }
});

test('reenable requires intact version and preserves emergency audit and replay state', function (): void {
    $f = gatewayFixture();
    try {
        $security = app(AgentOfficeSecurity::class);
        app(AgentOfficeState::class)->claim('keep-nonce', 300);
        $state = file_get_contents($f['dir'].'/state.json');
        $security->disable();
        expect(fn () => $security->reenable(99, 123))->toThrow(RuntimeException::class);
        expect(fn () => $security->snapshot())->toThrow(RuntimeException::class);
        $security->reenable(1, 123);
        expect($security->snapshot()['version'])->toBe(1);
        expect(glob($f['dir'].'/registry.enc.disabled.*.audit'))->toHaveCount(1);
        expect(file_get_contents($f['dir'].'/state.json'))->toBe($state);
        expect(file_get_contents($f['dir'].'/audit.jsonl'))->toContain('emergency_reenable');
        $this->get('/api/v1/internal/agent-office/system-health', gatewayHeaders($f))->assertOk();
    } finally {
        gatewayCleanup($f);
    }
});

test('pending publication denies traffic until explicit version bound reconciliation', function (): void {
    $f = gatewayFixture();
    try {
        $security = app(AgentOfficeSecurity::class);
        file_put_contents($f['dir'].'/registry.enc.pending', 'synthetic interrupted change');
        expect($security->snapshot(false)['version'])->toBe(1);
        expect(fn () => $security->snapshot())->toThrow(RuntimeException::class, 'registry_change_pending');
        expect(fn () => $security->reenable(2, 123))->toThrow(RuntimeException::class, 'registry_version_conflict');
        $result = $security->replaceAllowlist([], 1, 123);
        expect($result['status'])->not->toBe('applied');
        expect($security->snapshot(false)['version'])->toBe(1);
        $security->reenable(1, 123);
        expect($security->snapshot()['version'])->toBe(1);
        expect(file_exists($f['dir'].'/registry.enc.pending'))->toBeFalse();
    } finally {
        gatewayCleanup($f);
    }
});

test('publisher keeps durable pending marker through intent and completion audit', function (): void {
    $f = gatewayFixture();
    try {
        $security = new class extends AgentOfficeSecurity
        {
            public array $pending = [];

            public function audit(array $entry): void
            {
                $this->pending[] = file_exists(config('agent_office.registry_path').'.pending');
                parent::audit($entry);
            }
        };
        expect($security->replaceAllowlist([], 1, 123)['status'])->toBe('applied');
        expect($security->pending)->toBe([true, true]);
        expect(file_exists($f['dir'].'/registry.enc.pending'))->toBeFalse();
    } finally {
        gatewayCleanup($f);
    }
});

test('redundant reenable does not emit a recovery event', function (): void {
    $f = gatewayFixture();
    try {
        $security = app(AgentOfficeSecurity::class);
        $before = is_file($f['dir'].'/audit.jsonl') ? file_get_contents($f['dir'].'/audit.jsonl') : '';
        expect(fn () => $security->reenable(1, 123))->toThrow(RuntimeException::class, 'reenable_failed');
        expect(is_file($f['dir'].'/audit.jsonl') ? file_get_contents($f['dir'].'/audit.jsonl') : '')->toBe($before);
    } finally {
        gatewayCleanup($f);
    }
});

test('reenable completion audit failure restores emergency disable', function (): void {
    $f = gatewayFixture();
    try {
        $security = new class extends AgentOfficeSecurity
        {
            public function audit(array $entry): void
            {
                if ($entry['event'] === 'emergency_reenable') {
                    throw new RuntimeException('audit_unavailable');
                }
                parent::audit($entry);
            }
        };
        $security->disable();
        expect(fn () => $security->reenable(1, 123))->toThrow(RuntimeException::class, 'audit_unavailable');
        expect(fn () => $security->snapshot())->toThrow(RuntimeException::class, 'emergency_disabled');
        $events = array_map(fn (string $line): string => json_decode($line, true, flags: JSON_THROW_ON_ERROR)['event'], file($f['dir'].'/audit.jsonl', FILE_IGNORE_NEW_LINES));
        expect($events)->toBe(['emergency_reenable_intent']);
    } finally {
        gatewayCleanup($f);
    }
});

test('completion audit failure reports unknown outcome and disables committed allowlist', function (): void {
    $f = gatewayFixture();
    try {
        $security = new class extends AgentOfficeSecurity
        {
            public function audit(array $entry): void
            {
                if ($entry['event'] === 'allowlist_applied') {
                    throw new RuntimeException('audit_unavailable');
                }
                parent::audit($entry);
            }
        };
        $result = $security->replaceAllowlist([], 1, 123);
        expect($result)->toBe(['status' => 'unknown', 'effective_version' => 2]);
        expect(fn () => $security->snapshot())->toThrow(RuntimeException::class, 'emergency_disabled');
        expect(file_get_contents($f['dir'].'/audit.jsonl'))->toContain('allowlist_publish_intent')->not->toContain('allowlist_applied');
    } finally {
        gatewayCleanup($f);
    }
});

test('allowlist disabled and expired entries deny and audit failure does not publish', function (): void {
    $f = gatewayFixture();
    try {
        $security = app(AgentOfficeSecurity::class);
        $entries = [['id' => 'synthetic-entry', 'cidr' => '127.0.0.1/32', 'label' => 'Fixture', 'enabled' => false, 'expires_at' => null]];
        $result = $security->replaceAllowlist($entries, 1, 123);
        expect($result['status'])->toBe('applied');
        expect($security->allowedIp('127.0.0.1', $security->snapshot()))->toBeFalse();
        $entries[0]['enabled'] = true;
        $entries[0]['expires_at'] = time() - 1;
        $security->replaceAllowlist($entries, 2, 123);
        expect($security->allowedIp('127.0.0.1', $security->snapshot()))->toBeFalse();
        config(['agent_office.audit_path' => $f['dir'].'/absent/audit.jsonl']);
        $entries[0]['expires_at'] = null;
        $result = $security->replaceAllowlist($entries, 3, 123);
        expect($result['status'])->toBe('failed')->and($result['effective_version'])->toBe(3);
        expect($security->allowedIp('127.0.0.1', $security->snapshot()))->toBeFalse();
    } finally {
        gatewayCleanup($f);
    }
});
