<?php

namespace Andriichuk\HttpLogger\Tests;

use Andriichuk\HttpLogger\HttpLoggerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            HttpLoggerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        config()->set('logging.channels.http', [
            'driver' => 'single',
            'path' => storage_path('logs/http.log'),
            'level' => 'debug',
        ]);
    }
}
