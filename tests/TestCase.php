<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\SmoothieServiceProvider;
use Dotenv\Dotenv;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        // We could be using either Dotenv 2.x or 3.x:
        if (method_exists(Dotenv::class, 'create')) {
            $dotenv = Dotenv::create(dirname(__DIR__));
        } else {
            $dotenv = new Dotenv(dirname(__DIR__));
        }
        $dotenv->load();
        $app['config']->set('database.default', 'smoothie');
        $app['config']->set('database.connections.smoothie', [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT'),
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [ SmoothieServiceProvider::class ];
    }

    protected function setUp() : void
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
