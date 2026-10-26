<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Fixture-only bootstrap: never load either project's .env or cached runtime config.
function agentOfficeFixtureApp(): Application
{
    $scratch = str_replace('\\', '/', (string) getenv('TMPDIR'));
    if (! is_dir($scratch) || ! str_ends_with(strtolower($scratch), '/hermes/cache/scratch')) {
        throw new RuntimeException('fixture_scratch_denied');
    }
    foreach (['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_CONFIG_CACHE' => $scratch.'/gateway-no-config.php', 'APP_ROUTES_CACHE' => $scratch.'/gateway-no-routes.php'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->useEnvironmentPath($scratch)->loadEnvironmentFrom('gateway-no-environment');
    $app->afterBootstrapping(LoadConfiguration::class, function () use ($scratch): void {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'app.debug' => false,
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql', 'url' => null, 'host' => '127.0.0.1', 'port' => '3306',
                'database' => 'db_actstation_test', 'username' => 'root', 'password' => '',
                'unix_socket' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '', 'strict' => true, 'engine' => null, 'options' => [],
            ],
            'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync',
            'mail.default' => 'array', 'broadcasting.default' => 'null',
            'logging.default' => 'null', 'agent_office.enabled' => false,
            'agent_office.allow_loopback_http' => false,
            // Legacy perimeter checks are separate from native OS ACL acceptance harness.
            'agent_office.private_acl_required' => false,
            'agent_office.audit_path' => $scratch.'/gateway-no-directory/audit.jsonl',
        ]);
    });

    return $app;
}

function agentOfficeFixtureGuard(): void
{
    $c = config('database.connections.mysql');
    if (! app()->environment('testing') || config('database.default') !== 'mysql'
        || ($c['driver'] ?? null) !== 'mysql' || $c['database'] !== 'db_actstation_test'
        || $c['host'] !== '127.0.0.1' || (string) $c['port'] !== '3306'
        || ! empty($c['url']) || ! empty($c['read']) || ! empty($c['write'])
        || ! empty($c['unix_socket']) || ! empty($c['prefix'])
        || DB::selectOne('SELECT DATABASE() AS db')->db !== 'db_actstation_test') {
        throw new RuntimeException('test_database_denied');
    }
    Http::preventStrayRequests();
}
