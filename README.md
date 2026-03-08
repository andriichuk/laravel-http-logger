# Laravel HTTP Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andriichuk/laravel-http-logger.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-http-logger)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-http-logger/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/andriichuk/laravel-http-logger/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-http-logger/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/andriichuk/laravel-http-logger/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/andriichuk/laravel-http-logger.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-http-logger)

A configurable HTTP request and response logger for Laravel, ideal for APIs. Logs requests and responses to a dedicated channel with optional sanitization of sensitive data, route and status filters, configurable headers, and optional inclusion of flashed validation errors (e.g. from form redirects) in the log context.

## Installation

**Requirements:** PHP 8.3+ and Laravel 11.x or 12.x.

Install the package via Composer:

```bash
composer require andriichuk/laravel-http-logger
```

Publish the config file:

```bash
php artisan vendor:publish --tag="http-logger-config"
```

Add a log channel for HTTP logs in `config/logging.php` (e.g. a dedicated file or stack):

```php
'channels' => [
    // ...
    'http' => [
        'driver' => 'daily',
        'path' => storage_path('logs/http.log'),
        'level' => 'debug',
    ],
],
```

## Configuration

After publishing, configure `config/http-logger.php` as needed.

**Environment variables:** `enabled` and `channel` can be driven by env: set `LOG_HTTP_REQUESTS` (default `false`) to enable logging, and `HTTP_LOG_CHANNEL` (default `'http'`) for the log channel name.

| Key | Description | Default |
|-----|-------------|---------|
| `enabled` | Master switch for HTTP logging. | `false` (or `env('LOG_HTTP_REQUESTS', false)`) |
| `channel` | Log channel name (must exist in `config/logging.php`). | `'http'` |
| `routes` | Laravel route patterns to log (with or without leading slash). `[]` = none; `['*']` = all; `['/api/*']` = API only. | `['/api/*']` |
| `report` | Which response status categories to log: `info` (1xx), `success` (2xx), `redirect` (3xx), `client_error` (4xx), `server_error` (5xx). Each key is a boolean. | `info`/`success`/`redirect` → `false`; `client_error`/`server_error` → `true` |
| `include_response` | Include response body in log context. | `true` |
| `include_request_headers` | Request header names (lowercase) to include. Use `['*']` for all. | `['x-app-version', 'x-device-id', 'x-device-type']` |
| `include_response_headers` | Response header names (lowercase) to include. Use `['*']` for all. | `[]` |
| `sensitive_fields` | Request/response body keys to replace with `***`. | `['token', 'refresh_token', 'password', …]` |
| `sensitive_headers` | Header names (lowercase) to replace with `***`. | `['authorization', 'cookie']` |
| `max_string_value_length` | Max length for string values in bodies (and non-JSON response body) before truncation. Use `null` to disable truncation. | `100` |
| `message_prefix` | Prefix for the log message. | `'[HttpLogger] '` |
| `include_host_in_message` | Include request origin (protocol + host, e.g. `https://example.com`) in the log message. | `false` |
| `include_session_errors` | When true, add flashed validation errors (e.g. from `redirect()->withErrors()`) to log context as `session_errors`. Read-only; does not consume flash. | `false` |

**Response body logging:** JSON responses (Content-Type `application/json`) are decoded and sanitized; `max_string_value_length` applies to each string value in the payload. Non-JSON responses (e.g. HTML or plain text) are logged as a single string and truncated when `max_string_value_length` is set.

### Example log output

Message: `[HttpLogger] POST /api/login`

Context (example):

```php
[
    'request_headers' => ['content-type' => ['application/json']],
    'response_headers' => ['content-type' => ['application/json']],
    'request' => ['email' => 'user@example.com', 'password' => '***'],
    'response' => ['token' => '***', 'user_id' => 1],
]
```

When `include_response` is `false`, `response` is the string `'skipped'`. When `include_session_errors` is `true` and the request has flashed validation errors, the context also includes a `session_errors` key (e.g. from form redirects).

### API-focused example

Log only API routes and 4xx/5xx responses, with masked auth and cookies:

```php
// config/http-logger.php
return [
    'enabled' => true,
    'channel' => 'http',
    'routes' => ['api/*'],
    'report' => [
        'info' => false,
        'success' => false,
        'redirect' => false,
        'client_error' => true,
        'server_error' => true,
    ],
    'include_response' => true,
    'include_request_headers' => ['content-type', 'x-request-id'],
    'include_response_headers' => ['content-type', 'x-request-id'],
    'sensitive_fields' => ['token', 'password', 'refresh_token', 'code'],
    'sensitive_headers' => ['authorization', 'cookie'],
    'max_string_value_length' => 100,
    'message_prefix' => '[HttpLogger] ',
    'include_host_in_message' => false,
    'include_session_errors' => false,
];
```

## Testing

Run the test suite with Pest:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Serhii Andriichuk](https://github.com/andriichuk)
- [All Contributors](https://github.com/andriichuk/laravel-http-logger/contributors)
