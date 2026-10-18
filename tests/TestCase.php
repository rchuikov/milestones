<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    private string $testStorage;

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')
            || $app['config']->get('database.default') !== 'mariadb'
            || $app['config']->get('database.connections.mariadb.database') !== 'testing'
            || filled($app['config']->get('database.connections.mariadb.url'))) {
            throw new LogicException('Tests require the dedicated MariaDB testing database. Clear cached configuration before running tests.');
        }

        $this->testStorage = sys_get_temp_dir().'/milestones-tests-'.bin2hex(random_bytes(8));
        foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs'] as $directory) {
            mkdir($this->testStorage.'/'.$directory, recursive: true);
        }
        $app->useStoragePath($this->testStorage);
        $app['config']->set('view.compiled', $this->testStorage.'/framework/views');

        return $app;
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->testStorage)) {
                File::deleteDirectory($this->testStorage);
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
