<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'mariadb'
            || $app['config']->get('database.connections.mariadb.database') !== 'testing'
            || filled($app['config']->get('database.connections.mariadb.url'))) {
            throw new LogicException('Tests require the dedicated MariaDB testing database. Clear cached configuration before running tests.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
