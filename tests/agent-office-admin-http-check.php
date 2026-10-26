<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\AgentOfficePrincipal;
use App\Models\User;
use App\Services\AgentOfficeAdminAuth;
use App\Services\AgentOfficeSecurity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/fixtures/agent_office_bootstrap.php';
$app = agentOfficeFixtureApp();
$app->make(Kernel::class)->bootstrap();
agentOfficeFixtureGuard();
$dir = str_replace('\\', '/', getenv('TMPDIR')).'/gateway-admin-http-'.bin2hex(random_bytes(8));
mkdir($dir, 0700);
$users = [];
$role = $permission = $principal = $server = null;
$permissionCreated = false;
$clients = [];
$exit = 1;
$count = 0;
function adminHttpAssert(bool $ok, string $case): void
{
    if (! $ok) {
        throw new RuntimeException($case);
    }
}
try {
    if (PHP_OS_FAMILY === 'Windows') {
        $ps = '$ErrorActionPreference="Stop";$p=$env:AGENT_OFFICE_ACL_DIRECTORY;$sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User;$acl=New-Object System.Security.AccessControl.DirectorySecurity;$acl.SetOwner($sid);$acl.SetAccessRuleProtection($true,$false);foreach($id in @($sid.Value,"S-1-5-18","S-1-5-32-544")){$s=New-Object System.Security.Principal.SecurityIdentifier($id);$r=New-Object System.Security.AccessControl.FileSystemAccessRule($s,"FullControl","ContainerInherit,ObjectInherit","None","Allow");$acl.AddAccessRule($r)};Set-Acl -LiteralPath $p -AclObject $acl';
        (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $ps], null, ['AGENT_OFFICE_ACL_DIRECTORY' => $dir]))->mustRun();
    }
    mkdir($dir.'/sessions', 0700);
    mkdir($dir.'/cache', 0700);
    $permission = Permission::where('name', 'setting.agent-office-security')->where('guard_name', 'web')->first();
    if (! $permission) {
        $permission = Permission::create(['name' => 'setting.agent-office-security', 'guard_name' => 'web']);
        $permissionCreated = true;
    }
    $role = Role::create(['name' => 'gateway-http-'.bin2hex(random_bytes(5)), 'guard_name' => 'web', 'panel_access' => 'internal']);
    $role->givePermissionTo($permission);
    $password = 'Synthetic-password-'.bin2hex(random_bytes(8));
    foreach (['operator', 'outsider'] as $kind) {
        $u = User::withoutEvents(fn () => User::factory()->create(['name' => 'Synthetic gateway '.$kind, 'is_active' => true, 'password' => Hash::make($password)]));
        $users[] = $u;
        $u->assignRole($role);
    }
    [$operator, $outsider] = $users;
    $principal = AgentOfficePrincipal::create(['name' => 'Synthetic admin HTTP health', 'enabled' => true]);
    $token = $principal->createToken('admin-fixture', ['agent:health-read'], now()->addHour())->plainTextToken;
    $secret = random_bytes(32);
    $gateway = ['enabled' => true, 'allow_loopback_http' => true, 'trusted_proxies' => [], 'operator_ids' => [$operator->id], 'private_acl_required' => true, 'master_key' => base64_encode(random_bytes(32)), 'registry_path' => $dir.'/registry.enc', 'state_path' => $dir.'/state.json', 'audit_path' => $dir.'/audit.jsonl', 'admin_mfa_path' => $dir.'/mfa.enc'];
    config(['agent_office' => $gateway]);
    $security = app(AgentOfficeSecurity::class);
    $security->publish(['version' => 1, 'enabled' => true, 'expires_at' => time() + 3600, 'allowlist' => [['id' => 'loopback', 'cidr' => '127.0.0.1/32', 'label' => 'Fixture loopback', 'enabled' => true, 'expires_at' => null]], 'keys' => ['http-fixture' => ['secret' => base64_encode($secret), 'principal_id' => $principal->id, 'expires_at' => time() + 3600, 'enabled' => true]]]);
    app(AgentOfficeAdminAuth::class)->enroll($operator->id, $dir.'/enrollment.json');
    $enrollment = json_decode(file_get_contents($dir.'/enrollment.json'), true, flags: JSON_THROW_ON_ERROR);
    $google = new Google2FA;
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $origin = 'http://127.0.0.1:'.$port;
    file_put_contents($dir.'/config.json', json_encode(['gateway' => $gateway, 'origin' => $origin], JSON_THROW_ON_ERROR));
    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, dirname(__DIR__).'/tests/fixtures/agent_office_admin_router.php'], dirname(__DIR__).'/public', ['GATEWAY_FIXTURE_CONFIG' => $dir.'/config.json']);
    $server->start();
    for ($i = 0; $i < 60; $i++) {
        $s = @fsockopen('127.0.0.1', $port, $errno, $errstr, .1);
        if ($s) {
            fclose($s);
            break;
        }
        usleep(100000);
    }
    $client = function () use (&$clients) {
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_COOKIEFILE => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 2]);
        $clients[] = $ch;

        return $ch;
    };
    $request = function ($ch, string $path, string $method = 'GET', array $data = [], array $headers = []) use ($origin, &$count): array {
        curl_setopt_array($ch, [CURLOPT_URL => $origin.$path, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => array_merge(['Accept: text/html'], $headers)]);
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $count++;
        adminHttpAssert(is_string($body) && $status !== 0, 'native_http_transport');

        return [$status, $body];
    };
    $csrf = function (string $html): string {
        adminHttpAssert((bool) preg_match('/name="_token" value="([^"]+)"/', $html, $m), 'csrf_form_missing');

        return html_entity_decode($m[1], ENT_QUOTES);
    };
    $login = function ($ch, User $user) use ($request, $csrf, $password): void {
        [$status, $body] = $request($ch, '/admin/login');
        adminHttpAssert($status === 200, 'login_form');
        [$status] = $request($ch, '/admin/login', 'POST', ['_token' => $csrf($body), 'email' => $user->email, 'password' => $password]);
        adminHttpAssert($status === 302, 'login_submit');
    };
    if (in_array('--browser', $argv, true)) {
        $browser = new Process(['node', __DIR__.'/fixtures/agent_office_admin_browser.mjs'], dirname(__DIR__));
        $browser->setInput(json_encode(['origin' => $origin, 'email' => $operator->email, 'password' => $password, 'otp' => $google->getCurrentOtp($enrollment['secret'])], JSON_THROW_ON_ERROR));
        $browser->setTimeout(120)->run();
        adminHttpAssert($browser->isSuccessful(), 'browser_uat_failed');
        $report = json_decode($browser->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        adminHttpAssert(($report['success'] ?? false) === true && $security->snapshot(false)['version'] === 2 && ! $security->snapshot(false)['allowlist'][0]['enabled'], 'browser_readback_failed');
        echo "Chrome admin HTTP: login/password+TOTP/native form publish2/mobile/focus passed; readback confirmed\n";
    } else {
        $a = $client();
        [$status] = $request($a, '/admin/settings/agent-office');
        adminHttpAssert($status === 302, 'guest_denial');
        $login($a, $operator);
        [$status, $body] = $request($a, '/admin/settings/agent-office');
        adminHttpAssert($status === 200 && str_contains($body, 'Keamanan Gateway Office') && ! str_contains($body, $enrollment['secret']), 'admin_form');
        $csrfA = $csrf($body);
        $payload = ['expected_version' => 1, 'entries' => [['id' => 'loopback', 'cidr' => '127.0.0.1/32', 'label' => 'Fixture', 'enabled' => '0', 'expires_at' => '']]];
        [$status] = $request($a, '/admin/settings/agent-office', 'PUT', $payload, ['Origin: https://untrusted.invalid', 'Sec-Fetch-Site: cross-site']);
        adminHttpAssert($status === 419 && $security->snapshot(false)['version'] === 1, 'csrf_real_denial');
        $payload['_token'] = $csrfA;
        [$status] = $request($a, '/admin/settings/agent-office', 'PUT', $payload);
        adminHttpAssert($status === 403, 'missing_reauth');
        $otp = $google->getCurrentOtp($enrollment['secret']);
        [$status] = $request($a, '/admin/settings/agent-office/reauth', 'POST', ['_token' => $csrfA, 'password' => $password, 'otp' => $otp]);
        adminHttpAssert($status === 302, 'reauth_submit');
        $b = $client();
        $login($b, $operator);
        [$status, $body] = $request($b, '/admin/settings/agent-office');
        adminHttpAssert($status === 200, 'second_session_form');
        $otherPayload = $payload;
        $otherPayload['_token'] = $csrf($body);
        [$status] = $request($b, '/admin/settings/agent-office', 'PUT', $otherPayload);
        adminHttpAssert($status === 403 && $security->snapshot(false)['version'] === 1, 'session_binding');
        [$status] = $request($a, '/admin/settings/agent-office', 'PUT', $payload);
        adminHttpAssert($status === 302 && $security->snapshot(false)['version'] === 2 && ! $security->snapshot(false)['allowlist'][0]['enabled'], 'publish_effective');
        [$status, $body] = $request($a, '/admin/settings/agent-office');
        adminHttpAssert($status === 200 && str_contains($body, 'Status applied') && str_contains($body, 'Versi efektif 2'), 'applied_readback_ui');
        [$status] = $request($a, '/admin/settings/agent-office', 'PUT', $payload);
        adminHttpAssert($status === 403, 'proof_single_use');
        [$status] = $request($a, '/admin/settings/agent-office/reauth', 'POST', ['_token' => $csrfA, 'password' => $password, 'otp' => $otp]);
        adminHttpAssert($status === 302, 'replay_response');
        [$status, $body] = $request($a, '/admin/settings/agent-office');
        adminHttpAssert(str_contains($body, 'Verifikasi ditolak'), 'otp_replay_denial');
        $c = $client();
        $login($c, $outsider);
        [$status] = $request($c, '/admin/settings/agent-office');
        adminHttpAssert($status === 403, 'unappointed_operator');
        $ts = (string) time();
        $nonce = bin2hex(random_bytes(32));
        $target = '/api/v1/internal/agent-office/system-health';
        $headers = ['Accept: application/json', 'Authorization: Bearer '.$token, 'X-Agent-Key-Id: http-fixture', 'X-Agent-Timestamp: '.$ts, 'X-Agent-Nonce: '.$nonce, 'X-Agent-Signature: '.$security->signature('GET', $target, $ts, $nonce, 'http-fixture', $token, '', '', $secret)];
        [$status] = $request($client(), $target, headers: $headers);
        adminHttpAssert($status === 403, 'next_request_revoked');
        $audit = file_get_contents($dir.'/audit.jsonl');
        adminHttpAssert(str_contains($audit, 'allowlist_applied') && ! str_contains($audit, $password) && ! str_contains($audit, $enrollment['secret']) && ! str_contains($audit, $otp) && ! str_contains($audit, $token), 'audit_redaction');
        echo 'Native admin HTTP: '.$count." requests passed; real login/CSRF419/password+TOTP/session isolation/one-use/replay/operator403/publish2/readback/next-health403; native private ACL active\n";
    }
    $exit = 0;
} catch (Throwable $e) {
    fwrite(STDERR, 'admin_fixture_failed: '.(preg_match('/^[a-z_]+$/D', $e->getMessage()) ? $e->getMessage() : get_class($e))."\n");
} finally {
    try {
        foreach ($clients as $ch) {
            curl_close($ch);
        }
        if ($server) {
            $server->stop(1);
            $s = @fsockopen('127.0.0.1', $port, $errno, $errstr, .2);
            adminHttpAssert(! $server->isRunning() && ! $s, 'fixture_listener_survived');
        }
        if ($principal) {
            $principal->tokens()->delete();
            $id = $principal->id;
            $principal->delete();
            adminHttpAssert(! AgentOfficePrincipal::whereKey($id)->exists(), 'principal_cleanup');
        }
        foreach ($users as $user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            ActivityLog::where('user_id', $user->id)->delete();
            $user->syncRoles([]);
            $id = $user->id;
            User::withoutEvents(fn () => $user->forceDelete());
            adminHttpAssert(! User::withTrashed()->whereKey($id)->exists(), 'user_cleanup');
        }
        $role?->delete();
        if ($permissionCreated) {
            $permission?->delete();
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
        adminHttpAssert(! is_dir($dir), 'directory_cleanup');
        echo "Admin cleanup readback: synthetic users/principal absent, fixture process/listener closed, private directory absent\n";
    } catch (Throwable $e) {
        $exit = 1;
        fwrite(STDERR, 'admin_cleanup_failed: '.get_class($e)."\n");
    }
}
exit($exit);
