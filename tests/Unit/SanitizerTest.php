<?php

use Andriichuk\HttpLogger\Sanitizer;

beforeEach(function () {
    $this->sanitizer = new Sanitizer;
});

it('replaces sensitive keys with mask', function () {
    $data = ['token' => 'secret', 'email' => 'u@x.com'];
    $result = $this->sanitizer->sanitize($data, ['token'], 100);

    expect($result)->toBe(['token' => '***', 'email' => 'u@x.com']);
});

it('uses default sensitive keys when null passed', function () {
    $data = ['token' => 'secret', 'refresh_token' => 'rt', 'other' => 'ok'];
    $result = $this->sanitizer->sanitize($data, null, null);

    expect($result['token'])->toBe('***')
        ->and($result['refresh_token'])->toBe('***')
        ->and($result['other'])->toBe('ok');
});

it('sanitizes nested arrays recursively', function () {
    $data = ['user' => ['token' => 'secret', 'name' => 'John']];
    $result = $this->sanitizer->sanitize($data, ['token'], 100);

    expect($result)->toBe(['user' => ['token' => '***', 'name' => 'John']]);
});

it('truncates long strings with ellipsis', function () {
    $long = str_repeat('a', 150);
    $result = $this->sanitizer->sanitize(['text' => $long], [], 100);

    expect($result['text'])->toBe(str_repeat('a', 100).'…');
});

it('uses configurable max length', function () {
    $text = str_repeat('x', 50);
    $result = $this->sanitizer->sanitize(['t' => $text], [], 10);

    expect($result['t'])->toBe(str_repeat('x', 10).'…');
});

it('leaves short strings unchanged', function () {
    $data = ['short' => 'ok'];
    $result = $this->sanitizer->sanitize($data, [], 100);

    expect($result['short'])->toBe('ok');
});

it('sanitizes recursively with same sensitive keys and max length', function () {
    $data = [
        'level1' => [
            'level2' => ['password' => 'hidden', 'desc' => str_repeat('x', 200)],
        ],
    ];
    $result = $this->sanitizer->sanitize($data, ['password'], 50);

    expect($result['level1']['level2']['password'])->toBe('***');
    expect($result['level1']['level2']['desc'])->toBe(str_repeat('x', 50).'…');
});
