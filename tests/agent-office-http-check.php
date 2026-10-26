<?php

declare(strict_types=1);

use App\Models\AgentOfficePrincipal;
use App\Services\AgentOfficeSecurity;
use App\Services\OfficeCoreHealth;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/fixtures/agent_office_bootstrap.php';
require dirname(__DIR__, 2).'/ActStationOffice/app/Services/OfficeCoreHealth.php';
require dirname(__DIR__, 2).'/ActStationOffice/app/Services/OfficePublicReader.php';
require dirname(__DIR__, 2).'/ActStationOffice/app/Services/OfficeProviderTransport.php';
$app = agentOfficeFixtureApp();
$app->make(Kernel::class)->bootstrap();
agentOfficeFixtureGuard();

class NativeOfficeHealthFixture extends OfficeCoreHealth
{
    public ?array $fixedHeaders = null;

    public array $lastHeaders = [];

    protected function requestHeaders(string $keyId, #[SensitiveParameter] string $token, #[SensitiveParameter] string $secret): array
    {
        return $this->lastHeaders = $this->fixedHeaders ?? parent::requestHeaders($keyId, $token, $secret);
    }
}

function nativeGatewayCheck(bool $ok, string $case): void
{
    if (! $ok) {
        throw new RuntimeException($case);
    }
}
$dir = str_replace('\\', '/', getenv('TMPDIR')).'/gateway-http-'.bin2hex(random_bytes(8));
mkdir($dir, 0700, true);
$principal = null;
$server = null;
$exit = 1;
try {
    // Signer regression only; native ACL is exercised by admin/publisher/deployment checks.
    $config = ['enabled' => true, 'allow_loopback_http' => true, 'private_acl_required' => false, 'master_key' => base64_encode(random_bytes(32)), 'registry_path' => $dir.'/registry.enc', 'state_path' => $dir.'/state.json', 'audit_path' => $dir.'/audit.jsonl'];
    config(['agent_office' => $config]);
    $principal = AgentOfficePrincipal::create(['name' => 'Synthetic native gateway '.bin2hex(random_bytes(4)), 'enabled' => true]);
    $token = $principal->createToken('native-fixture', ['agent:health-read'], now()->addHour())->plainTextToken;
    $secret = random_bytes(32);
    $snapshot = ['version' => 1, 'enabled' => true, 'expires_at' => time() + 3600, 'allowlist' => [['cidr' => '127.0.0.1/32', 'expires_at' => null]], 'keys' => ['native-fixture' => ['secret' => base64_encode($secret), 'principal_id' => $principal->id, 'expires_at' => time() + 3600, 'enabled' => true]]];
    $security = app(AgentOfficeSecurity::class);
    $security->publish($snapshot);
    touch($dir.'/config.json');
    chmod($dir.'/config.json', 0600);
    file_put_contents($dir.'/config.json', json_encode($config, JSON_THROW_ON_ERROR));
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, dirname(__DIR__).'/tests/fixtures/agent_office_router.php'], dirname(__DIR__).'/public', ['GATEWAY_FIXTURE_CONFIG' => $dir.'/config.json']);
    $server->start();
    $ready = false;
    for ($i = 0; $i < 60; $i++) {
        $s = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($s) {
            fclose($s);
            $ready = true;
            break;
        } usleep(100000);
    }
    if (! $ready) {
        throw new RuntimeException('fixture_server_unavailable');
    }
    $path = '/api/v1/internal/agent-office/system-health';
    $origin = 'http://127.0.0.1:'.$port;
    $office = new NativeOfficeHealthFixture;
    $calls = 0;
    $check = function (string $outcome, int $status, string $case) use ($office, $origin, &$token, $secret, &$calls): array {
        $result = $office->health($origin, 'native-fixture', $token, $secret, true);
        $calls++;
        nativeGatewayCheck(($result['outcome'] ?? null) === $outcome && ($result['http_status'] ?? null) === $status, $case);

        return $result;
    };
    $health = $check('healthy', 200, 'native_office_health_failed');
    nativeGatewayCheck($health['data']['database'] === 'connected' && $health['data']['n8n_status'] === 'not_checked' && is_numeric($health['data']['latency_ms']), 'native_select_one_shape_failed');
    $office->fixedHeaders = $office->lastHeaders;
    $check('gateway_denied', 409, 'native_replay_failed');
    $office->fixedHeaders = null;
    $raw = function (array $headers, string $target, string $body = '') use ($origin, &$calls): int {
        $ch = curl_init($origin.$target);
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*']);
        if ($body !== '') {
            curl_setopt_array($ch, [CURLOPT_POSTFIELDS => $body, CURLOPT_CUSTOMREQUEST => 'GET']);
        }
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $calls++;

        return $status;
    };
    $fresh = function (string $target = '/api/v1/internal/agent-office/system-health', ?int $timestamp = null) use (&$token, $secret): array {
        $ts = (string) ($timestamp ?? time());
        $nonce = bin2hex(random_bytes(32));

        return ['Accept: application/json', 'Authorization: Bearer '.$token, 'X-Agent-Key-Id: native-fixture', 'X-Agent-Timestamp: '.$ts, 'X-Agent-Nonce: '.$nonce, 'X-Agent-Signature: '.OfficeCoreHealth::signature('GET', $target, $ts, $nonce, 'native-fixture', $token, '', '', $secret)];
    };
    $bad = $fresh();
    $bad[5] = 'X-Agent-Signature: '.str_repeat('0', 64);
    $office->fixedHeaders = $bad;
    $check('gateway_denied', 403, 'native_signature_failed');
    $office->fixedHeaders = $fresh(timestamp: time() - 61);
    $check('gateway_denied', 403, 'native_stale_failed');
    $office->fixedHeaders = null;
    nativeGatewayCheck($raw($fresh(), $path, '[]') === 403, 'native_get_body_failed');
    $query = $path.'?z=2&x=%C3%A9';
    nativeGatewayCheck($raw($fresh($query), $query) === 422, 'native_query_failed');
    $duplicate = $fresh();
    $duplicate[] = 'X-Agent-Key-Id: native-fixture';
    nativeGatewayCheck($raw($duplicate, $path) === 403, 'native_duplicate_header_failed');
    $originalToken = $token;
    $token = $principal->createToken('wrong-ability', ['agent:health'], now()->addHour())->plainTextToken;
    $check('gateway_denied', 403, 'native_ability_failed');
    $token = $originalToken;
    $principal->update(['enabled' => false]);
    $check('gateway_denied', 403, 'native_principal_revocation_failed');
    $principal->update(['enabled' => true]);
    $check('healthy', 200, 'native_principal_restore_failed');
    $principal->tokens()->whereKey(explode('|', $token)[0])->delete();
    $check('gateway_denied', 401, 'native_token_revocation_failed');
    $token = $principal->createToken('replacement-fixture', ['agent:health-read'], now()->addHour())->plainTextToken;
    $snapshot['keys']['native-fixture']['enabled'] = false;
    $snapshot['version']++;
    $security->publish($snapshot);
    $check('gateway_denied', 403, 'native_key_revocation_failed');
    $audit = file_get_contents($dir.'/audit.jsonl');
    $entries = array_filter(explode("\n", trim($audit)));
    nativeGatewayCheck(count($entries) === $calls, 'native_request_count_failed');
    nativeGatewayCheck(! str_contains($audit, $token) && ! str_contains($audit, $originalToken) && ! str_contains($audit, $secret) && ! str_contains($audit, base64_encode($secret)), 'native_audit_secret_failed');
    echo 'Office actual native HTTP: health200/SELECT1, replay409, key403/principal403/token401 revocation, signature403/stale403/ability403/body403/query422/duplicate403; requests='.$calls.' audit='.count($entries)."; no retry\n";
    $exit = 0;
} catch (Throwable $error) {
    $reason = in_array($error->getMessage(), ['native_office_health_failed', 'native_select_one_shape_failed', 'native_replay_failed', 'native_signature_failed', 'native_stale_failed', 'native_get_body_failed', 'native_query_failed', 'native_duplicate_header_failed', 'native_ability_failed', 'native_principal_revocation_failed', 'native_principal_restore_failed', 'native_token_revocation_failed', 'native_key_revocation_failed', 'native_request_count_failed', 'native_audit_secret_failed'], true) ? $error->getMessage() : 'native_gateway_check_failed';
    fwrite(STDERR, $reason."\n");
} finally {
    try {
        if ($server) {
            $server->stop(1);
            $listener = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($listener) {
                fclose($listener);
            }
            nativeGatewayCheck(! $server->isRunning() && ! $listener, 'fixture_server_cleanup_failed');
        }
    } catch (Throwable) {
        $exit = 1;
        fwrite(STDERR, "fixture_server_cleanup_failed\n");
    }
    try {
        if ($principal) {
            $principal->tokens()->delete();
            $id = $principal->id;
            $principal->delete();
            nativeGatewayCheck(! AgentOfficePrincipal::whereKey($id)->exists() && ! DB::table('personal_access_tokens')->where('tokenable_type', AgentOfficePrincipal::class)->where('tokenable_id', $id)->exists(), 'fixture_database_cleanup_failed');
        }
    } catch (Throwable) {
        $exit = 1;
        fwrite(STDERR, "fixture_database_cleanup_failed\n");
    }
    try {
        foreach (glob($dir.'/*') as $file) {
            unlink($file);
        }
        rmdir($dir);
        nativeGatewayCheck(! is_dir($dir), 'fixture_directory_cleanup_failed');
    } catch (Throwable) {
        $exit = 1;
        fwrite(STDERR, "fixture_directory_cleanup_failed\n");
    }
    if ($exit === 0) {
        echo "cleanup readback: fixture principals/tokens0, registry/config directory absent, server listener closed\n";
    }
}
exit($exit);
