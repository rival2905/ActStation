<?php

declare(strict_types=1);
use App\Services\AgentOfficeState;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = new Application(dirname(__DIR__, 2));
Container::setInstance($app);
// Shared-state race only; native OS ACL acceptance is exercised separately.
$app->instance('config', new Repository(['agent_office' => ['state_path' => $argv[1], 'private_acl_required' => false]]));
$app->instance('path.public', dirname(__DIR__, 2).'/public');
$app->instance('path', dirname(__DIR__, 2).'/app');
$state = new AgentOfficeState;
$ok = $argv[2] === 'claim' ? $state->claim('same-nonce', 300) : $state->hit('same-principal', 1, 60);
echo $ok ? 'winner' : 'denied';
