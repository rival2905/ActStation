<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AgentOfficeAdminAuth;
use App\Services\AgentOfficeSecurity;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function adminGateFixture(): array
{
    $f = gatewayFixture();
    $user = User::withoutEvents(fn () => User::factory()->create(['is_active' => true, 'password' => Hash::make('Synthetic-password-only')]));
    $permission = Permission::firstOrCreate(['name' => 'setting.agent-office-security', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'gateway-fixture-'.bin2hex(random_bytes(4)), 'guard_name' => 'web', 'panel_access' => 'internal']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);
    config(['agent_office.operator_ids' => [$user->id], 'agent_office.admin_mfa_path' => $f['dir'].'/mfa.enc']);
    config(['session.driver' => 'file', 'session.files' => $f['dir'], 'cache.default' => 'file', 'cache.stores.file.path' => $f['dir'].'/cache', 'cache.stores.file.lock_path' => $f['dir'].'/cache']);
    try {
        app(AgentOfficeAdminAuth::class)->enroll($user->id, $f['dir'].'/enroll.json');
        $enrollment = json_decode(file_get_contents($f['dir'].'/enroll.json'), true, flags: JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        gatewayCleanup($f);
        throw $e;
    }
    $f += compact('user', 'role', 'enrollment');

    return $f;
}

function adminGatePayload(int $version = 1): array
{
    return ['expected_version' => $version, 'entries' => [['id' => 'fixture-entry', 'cidr' => '127.0.0.1/32', 'label' => 'Synthetic', 'enabled' => '1', 'expires_at' => null]]];
}

test('designated human password totp reauth publishes then proof cannot be reused', function (): void {

    $f = adminGateFixture();
    try {
        $this->actingAs($f['user'])->get('/admin/settings/agent-office')->assertOk()->assertSee('Keamanan Gateway Office')->assertSee('href="'.route('admin.settings.agent-office').'"', false)->assertDontSee($f['enrollment']['secret']);
        $otp = (new Google2FA)->getCurrentOtp($f['enrollment']['secret']);
        $this->post('/admin/settings/agent-office/reauth', ['password' => 'Synthetic-password-only', 'otp' => $otp])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
        $payload = adminGatePayload();
        $payload['entries'][] = ['id' => 'new', 'cidr' => '', 'label' => '', 'enabled' => '1', 'expires_at' => ''];
        $this->put('/admin/settings/agent-office', $payload)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('gateway_status', 'applied');
        expect(app(AgentOfficeSecurity::class)->snapshot(false)['version'])->toBe(2);
        $this->put('/admin/settings/agent-office', adminGatePayload(2))->assertForbidden();
        $this->post('/admin/settings/agent-office/reauth', ['password' => 'Synthetic-password-only', 'otp' => $otp])->assertSessionHasErrors('reauth');
    } finally {
        gatewayCleanup($f);
    }
});

test('permission alone and unappointed superadmin cannot administer gateway', function (): void {
    $f = adminGateFixture();
    try {
        config(['agent_office.operator_ids' => []]);
        $this->actingAs($f['user'])->get('/admin/settings/agent-office')->assertForbidden();
        config(['agent_office.operator_ids' => [$f['user']->id]]);
        $f['role']->revokePermissionTo('setting.agent-office-security');
        $this->get('/admin/settings/agent-office')->assertForbidden();
        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'], ['panel_access' => 'internal']);
        $f['user']->assignRole($superadmin);
        expect($f['user']->fresh()->can('setting.agent-office-security'))->toBeTrue();
        $this->get('/admin/settings/agent-office')->assertForbidden();
        config(['agent_office.operator_ids' => []]);
        $this->get('/admin/settings/agent-office')->assertForbidden();
    } finally {
        gatewayCleanup($f);
    }
});

test('invalid cidr broad risk and stale version cannot publish', function (): void {
    $f = adminGateFixture();
    try {
        $this->actingAs($f['user']);
        $bad = adminGatePayload();
        foreach (['127.0.0.5/24', '0.0.0.0/0', '::/0', 'localhost', ['invalid']] as $cidr) {
            $bad['entries'][0]['cidr'] = $cidr;
            $this->put('/admin/settings/agent-office', $bad)->assertSessionHasErrors('entries.0.cidr');
        }
        $bad['entries'][0]['cidr'] = '10.0.0.0/8';
        $this->put('/admin/settings/agent-office', $bad)->assertSessionHasErrors('confirm_broad_risk');
        $bad = adminGatePayload();
        $bad['entries'][0]['expires_at'] = '2000-01-01T00:00';
        $this->put('/admin/settings/agent-office', $bad)->assertSessionHasErrors('entries.0.expires_at');
        $bad = adminGatePayload();
        $bad['entries'][] = $bad['entries'][0];
        $this->put('/admin/settings/agent-office', $bad)->assertSessionHasErrors('entries.0.id');
        $otp = (new Google2FA)->getCurrentOtp($f['enrollment']['secret']);
        $this->post('/admin/settings/agent-office/reauth', ['password' => 'Synthetic-password-only', 'otp' => $otp])->assertRedirect()->assertSessionHasNoErrors();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->put('/admin/settings/agent-office', adminGatePayload(999))->assertStatus(409);
        expect(app(AgentOfficeSecurity::class)->snapshot(false)['version'])->toBe(1);
    } finally {
        gatewayCleanup($f);
    }
});

test('invalid reauth input never flashes otp or password', function (): void {
    $f = adminGateFixture();
    try {
        $this->actingAs($f['user'])->post('/admin/settings/agent-office/reauth', ['password' => 'Synthetic-password-only', 'otp' => 'invalid-otp-canary'])
            ->assertSessionHasErrors('otp')->assertSessionMissing('_old_input.otp')->assertSessionMissing('_old_input.password');
    } finally {
        gatewayCleanup($f);
    }
});

test('process local session or lock driver cannot authorize admin', function (): void {
    $f = adminGateFixture();
    try {
        config(['session.driver' => 'array']);
        $this->actingAs($f['user'])->get('/admin/settings/agent-office')->assertStatus(503);
        config(['session.driver' => 'file', 'cache.default' => 'array']);
        $this->get('/admin/settings/agent-office')->assertStatus(503);
    } finally {
        gatewayCleanup($f);
    }
});

test('session account operator and time changes invalidate single use proof', function (string $change): void {
    $f = adminGateFixture();
    try {
        $otp = (new Google2FA)->getCurrentOtp($f['enrollment']['secret']);
        $this->actingAs($f['user'])->post('/admin/settings/agent-office/reauth', ['password' => 'Synthetic-password-only', 'otp' => $otp])->assertRedirect()->assertSessionHasNoErrors();
        if ($change === 'session') {
            session()->migrate();
        } elseif ($change === 'password') {
            $f['user']->forceFill(['password' => Hash::make('Changed-synthetic-password')])->saveQuietly();
        } elseif ($change === 'operator') {
            config(['agent_office.operator_ids' => [$f['user']->id, 9999999]]);
        } elseif ($change === 'inactive') {
            $f['user']->forceFill(['is_active' => false])->saveQuietly();
        } elseif ($change === 'mfa') {
            app(AgentOfficeAdminAuth::class)->enroll($f['user']->id + 1000000, $f['dir'].'/second-enrollment.json');
        } else {
            $proof = session('agent_office_reauth');
            $proof['expires'] = time() - 1;
            session()->put('agent_office_reauth', $proof);
            session()->save();
        }
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->put('/admin/settings/agent-office', adminGatePayload())->assertForbidden();
        expect(app(AgentOfficeSecurity::class)->snapshot(false)['version'])->toBe(1);
    } finally {
        gatewayCleanup($f);
    }
})->with(['session', 'password', 'operator', 'inactive', 'expired', 'mfa']);

test('console enrollment refuses overwrite and unappointed operator', function (): void {
    $f = adminGateFixture();
    try {
        $output = $f['dir'].'/console-enrollment.json';
        $this->artisan('agent-office:enroll-mfa', ['--user' => (string) $f['user']->id, '--output' => $output])->assertFailed();
        expect(file_exists($output))->toBeFalse();
        $store = $f['dir'].'/console-mfa.enc';
        config(['agent_office.admin_mfa_path' => $store]);
        $this->artisan('agent-office:enroll-mfa', ['--user' => (string) $f['user']->id, '--output' => $output])->assertSuccessful();
        expect(is_file($output))->toBeTrue()->and(app(AgentOfficeAdminAuth::class)->isEnrolled($f['user']->id))->toBeTrue();
        $bytes = file_get_contents($store);
        $this->artisan('agent-office:enroll-mfa', ['--user' => (string) $f['user']->id, '--output' => $output])->assertFailed();
        expect(file_get_contents($store))->toBe($bytes);
        config(['agent_office.operator_ids' => []]);
        $this->artisan('agent-office:enroll-mfa', ['--user' => (string) $f['user']->id, '--output' => $f['dir'].'/denied.json'])->assertFailed();
        expect(file_exists($f['dir'].'/denied.json'))->toBeFalse();
    } finally {
        gatewayCleanup($f);
    }
});

test('emergency reenable needs confirmation and fresh one use proof', function (): void {
    $f = adminGateFixture();
    try {
        $security = app(AgentOfficeSecurity::class);
        $security->disable();
        $this->actingAs($f['user'])->post('/admin/settings/agent-office/reenable', ['expected_version' => 1, 'confirm_reenable' => 1])->assertForbidden();
        $otp = (new Google2FA)->getCurrentOtp($f['enrollment']['secret']);
        $this->post('/admin/settings/agent-office/reauth', ['password' => 'Synthetic-password-only', 'otp' => $otp])->assertRedirect()->assertSessionHasNoErrors();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post('/admin/settings/agent-office/reenable', ['expected_version' => 1])->assertSessionHasErrors('confirm_reenable');
        expect(file_exists($f['dir'].'/registry.enc.disabled'))->toBeTrue();
        $this->post('/admin/settings/agent-office/reenable', ['expected_version' => 1, 'confirm_reenable' => 1])->assertRedirect()->assertSessionHasNoErrors();
        expect($security->snapshot()['version'])->toBe(1)->and(glob($f['dir'].'/registry.enc.disabled.*.audit'))->toHaveCount(1);
        $this->post('/admin/settings/agent-office/reenable', ['expected_version' => 1, 'confirm_reenable' => 1])->assertForbidden();
    } finally {
        gatewayCleanup($f);
    }
});

test('interrupted publication exposes explicit recovery without emergency disable marker', function (): void {
    $f = adminGateFixture();
    try {
        file_put_contents($f['dir'].'/registry.enc.pending', 'synthetic interrupted change');
        $this->actingAs($f['user'])->get('/admin/settings/agent-office')->assertOk()->assertSee('Perubahan belum direkonsiliasi')->assertSee('confirm_reenable', false);
        $otp = (new Google2FA)->getCurrentOtp($f['enrollment']['secret']);
        $this->post('/admin/settings/agent-office/reauth', ['password' => 'Synthetic-password-only', 'otp' => $otp])->assertRedirect()->assertSessionHasNoErrors();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post('/admin/settings/agent-office/reenable', ['expected_version' => 1, 'confirm_reenable' => 1])->assertRedirect()->assertSessionHasNoErrors();
        expect(app(AgentOfficeSecurity::class)->snapshot()['version'])->toBe(1);
        expect(file_exists($f['dir'].'/registry.enc.pending'))->toBeFalse();
    } finally {
        gatewayCleanup($f);
    }
});

test('validation correction permits one mutation and failed publish is visible without reusable proof', function (): void {
    $f = adminGateFixture();
    try {
        $otp = (new Google2FA)->getCurrentOtp($f['enrollment']['secret']);
        $this->actingAs($f['user'])->post('/admin/settings/agent-office/reauth', ['password' => 'Synthetic-password-only', 'otp' => $otp])->assertRedirect()->assertSessionHasNoErrors();
        $this->withCookie(config('session.cookie'), session()->getId());
        $bad = adminGatePayload();
        $bad['entries'][0]['cidr'] = 'invalid';
        $this->put('/admin/settings/agent-office', $bad)->assertSessionHasErrors('entries.0.cidr');
        expect(app(AgentOfficeSecurity::class)->snapshot(false)['version'])->toBe(1);
        config(['agent_office.audit_path' => $f['dir'].'/missing/audit.jsonl']);
        $this->put('/admin/settings/agent-office', adminGatePayload())->assertRedirect()->assertSessionHas('gateway_status', 'failed')->assertSessionHas('gateway_effective_version', 1);
        $this->get('/admin/settings/agent-office')->assertOk()->assertSee('Status failed')->assertSee('alert-danger', false)->assertSee('Versi efektif 1');
        expect(app(AgentOfficeSecurity::class)->snapshot(false)['version'])->toBe(1);
        $this->put('/admin/settings/agent-office', adminGatePayload())->assertForbidden();
    } finally {
        gatewayCleanup($f);
    }
});
