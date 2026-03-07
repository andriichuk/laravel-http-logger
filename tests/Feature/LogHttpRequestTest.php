<?php

use Andriichuk\HttpLogger\Listeners\LogHttpRequest;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

it('does not log when disabled', function () {
    config()->set('http-logger.enabled', false);
    config()->set('http-logger.routes', ['*']);

    Log::shouldReceive('channel')->never();

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/test', 'GET');
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));
});

it('does not log when route does not match', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['api/*']);
    config()->set('http-logger.report.success', true);

    Log::shouldReceive('channel')->never();

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/web/other', 'GET');
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));
});

it('logs when enabled and route matches', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['api/*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.message_prefix', '[HttpLogger] ');
    config()->set('http-logger.include_response', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);
    config()->set('http-logger.max_body_length', 100);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('info')->once()->withArgs(function ($message, $context) use (&$logged) {
        $logged = ['message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/users', 'GET');
    $response = new Response('{"data":[]}', 200, ['Content-Type' => 'application/json']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['message'])->toBe('[HttpLogger] GET /api/users');
    expect($logged['context'])->toHaveKeys(['request_headers', 'response_headers', 'request', 'response']);
    expect($logged['context']['response'])->toBeArray();
});

it('skips logging when success is not reported', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', false);

    $mockChannel = Mockery::mock();
    $mockChannel->shouldNotReceive('info');
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/x', 'GET');
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));
});

it('skips logging when redirect is not reported', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.redirect', false);

    $mockChannel = Mockery::mock();
    $mockChannel->shouldNotReceive('info');
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/x', 'GET');
    $response = new Response('', 302, ['Location' => '/other']);
    $listener->handle(new RequestHandled($request, $response));
});

it('logs when client_error is reported and include_response false yields skipped', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.client_error', true);
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('info')->once()->withArgs(function ($msg, $ctx) use (&$logged) {
        $logged = ['message' => $msg, 'context' => $ctx];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/x', 'POST');
    $response = new Response('Unauthorized', 401);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['response'])->toBe('skipped');
});

it('masks sensitive fields in request and response', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.sensitive_fields', ['token', 'password']);
    config()->set('http-logger.include_response', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('info')->once()->withArgs(function ($msg, $ctx) use (&$logged) {
        $logged = ['context' => $ctx];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/login', 'POST', ['email' => 'u@x.com', 'token' => 'secret123']);
    $response = new Response('{"token":"new-secret"}', 200, ['Content-Type' => 'application/json']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['request']['token'])->toBe('***');
    expect($logged['context']['request']['email'])->toBe('u@x.com');
    expect($logged['context']['response']['token'])->toBe('***');
});

it('includes only configured request and response headers and masks sensitive ones', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.include_request_headers', ['x-custom', 'authorization']);
    config()->set('http-logger.include_response_headers', ['content-type']);
    config()->set('http-logger.sensitive_headers', ['authorization']);
    config()->set('http-logger.include_response', false);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('info')->once()->withArgs(function ($msg, $ctx) use (&$logged) {
        $logged = ['context' => $ctx];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/x', 'GET');
    $request->headers->set('X-Custom', 'value');
    $request->headers->set('Authorization', 'Bearer secret');
    $request->headers->set('X-Ignored', 'ignored');
    $response = new Response('ok', 200, ['Content-Type' => 'application/json']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['request_headers'])->toHaveKeys(['x-custom', 'authorization']);
    expect($logged['context']['request_headers']['authorization'])->toBe('***');
    expect($logged['context']['request_headers']['x-custom'])->toBe(['value']);
    expect($logged['context']['response_headers'])->toHaveKey('content-type');
});

it('includes all request and response headers when wildcard (*) is set', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.include_request_headers', ['*']);
    config()->set('http-logger.include_response_headers', ['*']);
    config()->set('http-logger.sensitive_headers', ['authorization']);
    config()->set('http-logger.include_response', false);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('info')->once()->withArgs(function ($msg, $ctx) use (&$logged) {
        $logged = ['context' => $ctx];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/x', 'GET');
    $request->headers->set('X-Custom', 'value');
    $request->headers->set('Authorization', 'Bearer secret');
    $response = new Response('ok', 200, ['Content-Type' => 'application/json', 'X-Response-Id' => '123']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['request_headers'])->toHaveKeys(['x-custom', 'authorization']);
    expect($logged['context']['request_headers']['authorization'])->toBe('***');
    expect($logged['context']['response_headers'])->toHaveKeys(['content-type', 'x-response-id']);
});
