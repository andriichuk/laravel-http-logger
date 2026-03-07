<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

it('logs request and response end-to-end when event is fired', function () {
    config()->set('http-logger.enabled', true);
    config()->set('http-logger.routes', ['*']);
    config()->set('http-logger.report.success', true);
    config()->set('http-logger.channel', 'http');
    config()->set('http-logger.include_response', true);
    config()->set('http-logger.include_request_headers', []);
    config()->set('http-logger.include_response_headers', []);
    config()->set('http-logger.sensitive_fields', []);
    config()->set('http-logger.sensitive_headers', []);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('info')->once()->withArgs(function ($message, $context) use (&$logged) {
        $logged = ['message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('http')->andReturn($mockChannel);

    $request = Request::create('/api/items', 'POST', ['name' => 'Foo']);
    $request->headers->set('Content-Type', 'application/json');
    $response = new Response('{"id":1}', 201, ['Content-Type' => 'application/json']);

    Event::dispatch(new RequestHandled($request, $response));

    expect($logged)->not->toBeEmpty();
    expect($logged['message'])->toBe('[HttpLogger] POST /api/items');
    expect($logged['context'])->toHaveKeys(['request_headers', 'response_headers', 'request', 'response']);
    expect($logged['context']['request'])->toBeArray();
    expect($logged['context']['response'])->toBeArray();
    expect($logged['context']['response'])->toHaveKey('id');
    expect($logged['context']['response']['id'])->toBe(1);
});
