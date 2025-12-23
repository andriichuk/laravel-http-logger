<?php

declare(strict_types=1);

namespace Andriichuk\HttpLogger;

use Andriichuk\HttpLogger\Commands\HttpLoggerCommand;
use Andriichuk\HttpLogger\Listeners\LogHttpRequest;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class HttpLoggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-http-logger')
            ->hasCommand(HttpLoggerCommand::class);
    }

    public function bootingPackage(): void
    {
        Event::listen(RequestHandled::class, LogHttpRequest::class);
    }
}
