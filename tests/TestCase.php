<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        if (getenv('AGENT_OFFICE_FIXTURE_TESTS') !== '1') {
            return parent::createApplication();
        }
        require_once __DIR__.'/fixtures/agent_office_bootstrap.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app = agentOfficeFixtureApp();
        $app->make(Kernel::class)->bootstrap();
        agentOfficeFixtureGuard();

        return $app;
    }
}
