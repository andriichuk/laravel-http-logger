<?php

declare(strict_types=1);

namespace Andriichuk\HttpLogger;

use Andriichuk\HttpLogger\Listeners\LogHttpRequest;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Registers the HTTP logger config and listens for RequestHandled to log requests/responses.
 */
final class HttpLoggerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-http-logger')
            ->hasConfigFile();
    }

    public function bootingPackage(): void
    {
        Event::listen(RequestHandled::class, LogHttpRequest::class);
    }
}
