<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/agent_office_bootstrap.php';
$app = agentOfficeFixtureApp();
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
agentOfficeFixtureGuard();
$path = (string) getenv('GATEWAY_FIXTURE_CONFIG');
$fixture = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
config(['agent_office' => $fixture['gateway'], 'session.driver' => 'file', 'session.files' => dirname($path).'/sessions', 'session.cookie' => 'gateway_fixture_session', 'session.secure' => false, 'session.domain' => null, 'session.block_store' => 'file', 'cache.default' => 'file', 'cache.stores.file.path' => dirname($path).'/cache', 'cache.stores.file.lock_path' => dirname($path).'/cache', 'session.lottery' => [0, 100], 'app.url' => $fixture['origin']]);
// Actual request-forgery middleware, without Laravel's testing shortcut.
$app->bind(PreventRequestForgery::class, fn () => new class($app, $app->make('encrypter')) extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
});
$request = Request::capture();
if ($request->isMethod('GET') && str_starts_with($request->getPathInfo(), '/assets/')) {
    $root = realpath(dirname(__DIR__, 2).'/public/assets');
    $asset = realpath(dirname(__DIR__, 2).'/public'.$request->getPathInfo());
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf'];
    $extension = strtolower(pathinfo($asset ?: '', PATHINFO_EXTENSION));
    if ($asset && str_starts_with(str_replace('\\', '/', $asset), str_replace('\\', '/', $root).'/') && isset($types[$extension])) {
        header('Content-Type: '.$types[$extension]);
        readfile($asset);
        exit;
    }
}
$allowed = ['/admin/login', '/admin/logout', '/admin/settings/agent-office', '/admin/settings/agent-office/reauth', '/admin/settings/agent-office/reenable', '/api/v1/internal/agent-office/system-health'];
if (! in_array($request->getPathInfo(), $allowed, true)) {
    http_response_code(404);
    exit;
}
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
