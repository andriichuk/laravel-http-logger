<?php

use Andriichuk\HttpLogger\HttpLoggerServiceProvider;

it('registers the package service provider', function () {
    $providers = $this->app->getLoadedProviders();
    expect($providers[HttpLoggerServiceProvider::class] ?? null)->toBeTrue();
});
