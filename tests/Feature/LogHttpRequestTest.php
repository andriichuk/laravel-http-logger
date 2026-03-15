<?php

use Andriichuk\HttpLogger\Listeners\LogHttpRequest;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;

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
    config()->set('http-logger.max_string_value_length', 100);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['level' => $level, 'message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/users', 'GET');
    $response = new Response('{"data":[]}', 200, ['Content-Type' => 'application/json']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['level'])->toBe('info');
    expect($logged['message'])->toBe('[HttpLogger] GET /api/users');
    expect($logged['context'])->toHaveKeys(['response_status_code', 'request_headers', 'response_headers', 'request_body', 'response_body']);
    expect($logged['context']['response_status_code'])->toBe(200);
    expect($logged['context']['response_body'])->toBeArray();
});

it('logs non-JSON response body (HTML or text) with truncation when include_response and include_non_json_response are true', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', true);
    config()->set('http-logger.include_non_json_response', true);
    config()->set('http-logger.max_string_value_length', 10);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/page', 'GET');
    $response = new Response('<html>Hello</html>', 200, ['Content-Type' => 'text/html']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['response_body'])->toBe('<html>Hell…');
});

it('logs non-JSON response as skipped when include_non_json_response is false', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', true);
    config()->set('http-logger.include_non_json_response', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/page', 'GET');
    $response = new Response('<html>Hello</html>', 200, ['Content-Type' => 'text/html']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['response_body'])->toBe('[skipped]');
});

it('logs full response and request body when max_string_value_length is null', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', true);
    config()->set('http-logger.include_non_json_response', true);
    config()->set('http-logger.max_string_value_length', null);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $longHtml = str_repeat('x', 500);
    $request = Request::create('/api/page', 'POST', ['key' => str_repeat('y', 300)]);
    $response = new Response($longHtml, 200, ['Content-Type' => 'text/html']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['response_body'])->toBe($longHtml);
    expect($logged['context']['request_body']['key'])->toBe(str_repeat('y', 300));
});

it('does not include host in log message when include_host_in_message is false', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.message_prefix', '[HttpLogger] ');
    config()->set('http-logger.include_host_in_message', false);
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message) use (&$logged) {
        $logged['message'] = $message;

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('https://api.example.com/users', 'GET');
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['message'])->toBe('[HttpLogger] GET /users');
});

it('includes protocol and host in log message when include_host_in_message is true', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.message_prefix', '[HttpLogger] ');
    config()->set('http-logger.include_host_in_message', true);
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message) use (&$logged) {
        $logged['message'] = $message;

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('https://api.example.com/users', 'GET');
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['message'])->toBe('[HttpLogger] GET https://api.example.com/users');
});

it('skips logging when success is not reported', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', false);

    $mockChannel = Mockery::mock();
    $mockChannel->shouldNotReceive('log');
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
    $mockChannel->shouldNotReceive('log');
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/x', 'GET');
    $response = new Response('', 302, ['Location' => '/other']);
    $listener->handle(new RequestHandled($request, $response));
});

it('logs when client_error is reported and include_response false yields skipped', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.report.client_error', true);
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $msg, $ctx) use (&$logged) {
        $logged = ['message' => $msg, 'context' => $ctx];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/x', 'POST');
    $response = new Response('Unauthorized', 401);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['response_body'])->toBe('[skipped]');
});

it('masks sensitive fields in request and response', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.sensitive_fields', ['token', 'password']);
    config()->set('http-logger.include_response', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $msg, $ctx) use (&$logged) {
        $logged = ['context' => $ctx];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/login', 'POST', ['email' => 'u@x.com', 'token' => 'secret123']);
    $response = new Response('{"token":"new-secret"}', 200, ['Content-Type' => 'application/json']);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context']['request_body']['token'])->toBe('***');
    expect($logged['context']['request_body']['email'])->toBe('u@x.com');
    expect($logged['context']['response_body']['token'])->toBe('***');
});

it('includes only configured request and response headers and masks sensitive ones', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.include_request_headers', ['x-custom', 'authorization']);
    config()->set('http-logger.include_response_headers', ['content-type']);
    config()->set('http-logger.sensitive_headers', ['authorization']);
    config()->set('http-logger.include_response', false);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $msg, $ctx) use (&$logged) {
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
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.include_request_headers', ['*']);
    config()->set('http-logger.include_response_headers', ['*']);
    config()->set('http-logger.sensitive_headers', ['authorization']);
    config()->set('http-logger.include_response', false);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $msg, $ctx) use (&$logged) {
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

it('does not add session_errors to context when include_session_errors is false', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_session_errors', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $session = $this->app->make('session')->driver('array');
    $session->start();
    $session->flash('errors', new MessageBag(['email' => ['The email field is required.']]));

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/form', 'POST');
    $request->setLaravelSession($session);
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context'])->not->toHaveKey('session_errors');
});

it('adds session_errors to context when include_session_errors is true and session has flashed errors', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_session_errors', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $session = $this->app->make('session')->driver('array');
    $session->start();
    $session->flash('errors', new MessageBag([
        'email' => ['The email field is required.'],
        'name' => ['The name may not be greater than 255 characters.'],
    ]));

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/form', 'POST');
    $request->setLaravelSession($session);
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context'])->toHaveKey('session_errors');
    expect($logged['context']['session_errors'])->toBe([
        'email' => ['The email field is required.'],
        'name' => ['The name may not be greater than 255 characters.'],
    ]);
});

it('does not add session_errors key when include_session_errors is true but session has no errors', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_session_errors', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/form', 'GET');
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context'])->not->toHaveKey('session_errors');
});

it('adds uploaded_files metadata to context when request has file uploads and include_uploaded_files_metadata is true', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_uploaded_files_metadata', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/upload', 'POST');
    $file = UploadedFile::fake()->create('document.pdf', 1, 'application/pdf'); // 1 KB
    $request->files->set('document', $file);
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context'])->toHaveKey('uploaded_files');
    expect($logged['context']['uploaded_files'])->toHaveCount(1);
    expect($logged['context']['uploaded_files'][0])->toMatchArray([
        'name' => 'document',
        'original_name' => 'document.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'error' => \UPLOAD_ERR_OK,
    ]);
    expect($logged['context']['uploaded_files'][0]['size'])->toBe(1024);
});

it('does not add uploaded_files to context when include_uploaded_files_metadata is false', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_uploaded_files_metadata', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/upload', 'POST');
    $request->files->set('document', UploadedFile::fake()->create('doc.pdf', 100));
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context'])->not->toHaveKey('uploaded_files');
});

it('does not add uploaded_files key when request has no file uploads', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_uploaded_files_metadata', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/upload', 'POST', ['title' => 'My doc']);
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context'])->not->toHaveKey('uploaded_files');
});

it('logs metadata for multiple uploaded files and nested file inputs', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_uploaded_files_metadata', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $message, $context) use (&$logged) {
        $logged = ['context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/upload', 'POST');
    $request->files->set('avatar', UploadedFile::fake()->image('avatar.jpg', 100, 100));
    $request->files->set('attachments', [
        UploadedFile::fake()->createWithContent('a.txt', 'hello'),
        UploadedFile::fake()->create('b.pdf', 200, 'application/pdf'),
    ]);
    $response = new Response('ok', 200);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context'])->toHaveKey('uploaded_files');
    expect($logged['context']['uploaded_files'])->toHaveCount(3);

    $names = array_column($logged['context']['uploaded_files'], 'name');
    $originalNames = array_column($logged['context']['uploaded_files'], 'original_name');
    expect($names)->toContain('avatar');
    expect($names)->toContain(0, 1); // nested array keys for attachments
    expect($originalNames)->toContain('avatar.jpg', 'a.txt', 'b.pdf');
});

it('includes response_status_code in log context', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.client_error', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function ($level, $msg, $ctx) use (&$logged) {
        $logged = ['level' => $level, 'context' => $ctx];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $request = Request::create('/api/not-found', 'GET');
    $response = new Response('Not Found', 404);
    $listener->handle(new RequestHandled($request, $response));

    expect($logged['context'])->toHaveKey('response_status_code');
    expect($logged['context']['response_status_code'])->toBe(404);
});

it('logs 5xx responses at error level and 2xx at info level', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.report.server_error', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.log_level_by_status', [
        'info' => 'info',
        'success' => 'info',
        'redirect' => 'info',
        'client_error' => 'warning',
        'server_error' => 'error',
    ]);
    config()->set('http-logger.include_response', false);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $loggedSuccess = [];
    $loggedError = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->twice()->withArgs(function ($level, $msg, $ctx) use (&$loggedSuccess, &$loggedError) {
        if ($ctx['response_status_code'] === 200) {
            $loggedSuccess['level'] = $level;
        }
        if ($ctx['response_status_code'] === 500) {
            $loggedError['level'] = $level;
        }

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $listener = $this->app->make(LogHttpRequest::class);
    $listener->handle(new RequestHandled(Request::create('/api/ok', 'GET'), new Response('ok', 200)));
    $listener->handle(new RequestHandled(Request::create('/api/fail', 'GET'), new Response('Server Error', 500)));

    expect($loggedSuccess['level'])->toBe('info');
    expect($loggedError['level'])->toBe('error');
});
