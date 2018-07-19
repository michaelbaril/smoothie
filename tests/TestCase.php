<?php

namespace Baril\Smoothie\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Baril\Smoothie\SmoothieServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'smoothie');
        $app['config']->set('database.connections.smoothie', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'smoothie',
            'username' => 'root',
            'password' => '',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [ SmoothieServiceProvider::class ];
    }

    protected function setUp()
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->withFactories(__DIR__ . '/database/factories');
        \DB::enableQueryLog();
    }

    protected function dumpQueryLog()
    {
        dump(\DB::getQueryLog());
    }
}
