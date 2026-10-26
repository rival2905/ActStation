<?php

declare(strict_types=1);

use App\Models\AgentOfficePrincipal;
use App\Services\AgentOfficeSecurity;
use Illuminate\Support\Facades\DB;

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
