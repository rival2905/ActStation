<?php

declare(strict_types=1);
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/agent_office_bootstrap.php';
$app = agentOfficeFixtureApp();
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
agentOfficeFixtureGuard();
$fixture = json_decode(file_get_contents(getenv('GATEWAY_FIXTURE_CONFIG')), true, 32, JSON_THROW_ON_ERROR);
config(['agent_office' => $fixture]);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
