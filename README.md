# Laravel HTTP Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andriichuk/laravel-http-logger.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-http-logger)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-http-logger/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/andriichuk/laravel-http-logger/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-http-logger/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/andriichuk/laravel-http-logger/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/andriichuk/laravel-http-logger.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-http-logger)

A **super simple**, configurable HTTP request and response logger for Laravel, ideal for APIs.

- **Sanitization** — Mask sensitive fields and headers (e.g. password, authorization).
- **Filters** — Limit by route patterns and response status (2xx, 4xx, 5xx, etc.).
- **Headers** — Choose which request/response headers to include in logs.
- **Session errors** — Optionally include flashed validation errors in log context.
- **File uploads** — Optionally log uploaded file metadata (name, size, MIME type); no file contents.

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

(Optional) Add a dedicated log channel for HTTP logs in `config/logging.php` (e.g. a separate file or stack). If you skip this, the package uses your default log channel.

```php
'channels' => [
    // ...
    'http' => [
        'driver' => 'daily',
        'path' => storage_path('logs/http.log'),
        'level' => 'info',
    ],
],
```

> **Important — when is logging on?** If `LOG_HTTP_REQUESTS` is not set in `.env`, the logger follows **`APP_DEBUG`**: it is enabled when `APP_DEBUG=true` (e.g. local) and disabled when `APP_DEBUG=false` (e.g. production). To force it on or off, set **`LOG_HTTP_REQUESTS=true`** or **`LOG_HTTP_REQUESTS=false`** in your `.env`, or set `'enabled'` in `config/http-logger.php`. The log channel can be overridden with **`HTTP_LOG_CHANNEL`** (e.g. `HTTP_LOG_CHANNEL=http` to use the channel above).

## Configuration

After publishing, configure `config/http-logger.php` as needed.

| Key | Description | Default |
| --- | --- | --- |
| `enabled`                         | Master switch for HTTP logging. When `LOG_HTTP_REQUESTS` is unset, falls back to `APP_DEBUG`.                                                                   | `env('LOG_HTTP_REQUESTS', APP_DEBUG)`                                         |
| `channel`                         | Log channel name (must exist in `config/logging.php`).                                                                                                          | `HTTP_LOG_CHANNEL` or `LOG_CHANNEL` or `'daily'`                              |
| `routes`                          | Laravel route patterns to log (with or without leading slash). `[]` = none; `['*']` = all; `['/api/*']` = API only.                                             | `['*']`                                                                       |
| `report`                          | Which response status categories to log: `info` (1xx), `success` (2xx), `redirect` (3xx), `client_error` (4xx), `server_error` (5xx). Each key is a boolean.    | `info`/`success` → `false`; `redirect`/`client_error`/`server_error` → `true` |
| `log_level_by_status`             | Map each status category to a PSR log level (`debug`, `info`, `notice`, `warning`, `error`, etc.). 5xx → `error` and 4xx → `warning` by default for easier filtering. | `client_error` → `warning`; `server_error` → `error`; others → `info`         |
| `include_response`                | Include response body in log context.                                                                                                                           | `true`                                                                        |
| `include_non_json_response`       | When `include_response` is true, include non-JSON bodies (HTML, text, etc.) in the log (truncated). Set to `true` to include them; default logs as `'[skipped]'`. | `false`                                                                       |
| `include_request_headers`         | Request header names (lowercase) to include. Use `['*']` for all.                                                                                               | `['*']`                                                                       |
| `include_response_headers`        | Response header names (lowercase) to include. Use `['*']` for all.                                                                                              | `[]`                                                                          |
| `sensitive_fields`                | Request/response body keys to replace with `***`.                                                                                                               | `['token', 'refresh_token', 'password', …]`                                   |
| `sensitive_headers`               | Header names (lowercase) to replace with `***`.                                                                                                                 | `['authorization', 'cookie']`                                                 |
| `max_string_value_length`         | Max length for string values in bodies (and non-JSON response body) before truncation. Use `null` to disable truncation.                                        | `100`                                                                         |
| `message_prefix`                  | Prefix for the log message.                                                                                                                                     | `'[HttpLogger] '`                                                             |
| `include_host_in_message`         | Include request origin (protocol + host, e.g. `https://example.com`) in the log message.                                                                        | `false`                                                                       |
| `include_session_errors`          | When true, add flashed validation errors (e.g. from `redirect()->withErrors()`) to log context as `session_errors`. Read-only; does not consume flash.          | `false`                                                                       |
| `include_uploaded_files_metadata` | When true, add metadata for uploaded files (original name, size, MIME type, extension) to log context as `uploaded_files`. No file contents are logged.         | `true`                                                                        |


**Response body logging:** JSON responses are decoded and sanitized; `max_string_value_length` applies to each string value. Non-JSON responses are logged as a truncated string or `'[skipped]'`. The log **level** follows response status by default (5xx → `error`, 4xx → `warning`, 1xx/2xx/3xx → `info`); configure via `log_level_by_status`.

### Example log output

**API validation error (422):** `WARNING` level, authorization and cookie masked, JSON response with validation errors.

```
[2026-03-08 12:02:01] local.WARNING: [HttpLogger] POST /v1/guest/autologin {"status_code":422,"request_headers":{"host":["api.example.com"],"content-type":["application/json"],"authorization":"***","cookie":"***"},"response_headers":[],"request":{"device":{"id":"device-hash","app_version":"1.0.0","model":"Pixel 10","platform":"android","os_version":"12.0.0"}},"response":{"message":"The device.locale field is required.","errors":{"device.locale":["The device.locale field is required."]}}}
```

**API file upload validation error (422):** `WARNING` level, file input shown as `[object]` in request body, `uploaded_files` metadata (name, size, mime_type, etc.) in context.

```
[2026-03-08 12:12:45] testing.WARNING: [HttpLogger] POST /api/profile/avatar {"status_code":422,"request_headers":{"host":["example.test"],"content-type":["application/x-www-form-urlencoded"],"x-app-version":["1.0.0"]},"response_headers":[],"request":{"avatar":"[object]"},"response":{"message":"The profile photo field must be an image. (and 1 more error)","errors":{"avatar":["The profile photo field must be an image.","The profile photo field must be a file of type: jpeg, jpg, png, gif, webp."]}},"uploaded_files":[{"name":"avatar","original_name":"document.pdf","size":102400,"mime_type":"application/pdf","extension":"pdf","error":0}]}
```

**Web auth form (redirect with flash):** `INFO` level, sensitive headers and fields masked, non-JSON response logged as `[skipped]`, `session_errors` with flashed validation message (e.g. login failure). Set `include_session_errors` to `true` in your config to get `session_errors` in the log.

```
[2026-03-08 12:25:04] local.INFO: [HttpLogger] POST /login {"status_code":302,"request_headers":{"host":["example.test"],"content-type":["application/x-www-form-urlencoded"],"cookie":"***"},"response_headers":[],"request":{"_token":"***","email":"user@example.com","password":"***"},"response":"[skipped]","session_errors":{"email":["These credentials do not match our records."]}}
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

