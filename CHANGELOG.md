# Changelog

All notable changes to `laravel-http-logger` will be documented in this file.

## 0.1.3 - 2025-03-08

### Added

- Option `include_session_errors` (default `false`) to add flashed validation errors (e.g. from form redirects) to log context as `session_errors`. Read-only; does not consume flash.

### Changed

- Config key `max_body_length` renamed to `max_string_value_length` (max length for string values and non-JSON response body). Set to `null` to disable truncation.
- Non-JSON response bodies (HTML, plain text) are now logged with truncation when `include_response` is true; previously they were logged as `'skipped'`.
- README: requirements, env vars, config defaults, response body behavior, and example context notes.

## 0.1.2 - 2025-03-08

### Added

- Option `include_host_in_message` (default `false`) to include request origin (protocol + host, e.g. `https://example.com`) in the log message.

### Changed

- Default `routes` config is now `['/api/*']` instead of `['*']`.
- Response body is only decoded and sanitized when response `Content-Type` is JSON; non-JSON responses are logged as `'skipped'`.

## 0.1.1 - 2025-03-07

### Changed

- Default `routes` config is now `['*']` (log all routes) instead of empty.
- Publish tag corrected to `http-logger-config` in README (was `laravel-http-logger-config`).
- Message prefix default set to `[HttpLogger] `.

### Added

- Header wildcard: set `include_request_headers` or `include_response_headers` to `['*']` to include all headers.
- PHP requirement relaxed to `^8.3` (support PHP 8.3 and 8.4).

### Fixed

- PHPStan: exclude config from analysis (env() rule); simplify response headers check in listener so CI passes.

## 0.1.0 - 2025-03-07

### Added

- Initial release.
- Configurable HTTP request/response logging via `RequestHandled` listener.
- Route filtering: log only matching patterns (e.g. `api/*`).
- Status filtering: choose which response types to log (1xx–5xx).
- Optional request and response headers allowlists with sensitive-header masking.
- Body sanitization: mask sensitive fields and truncate long strings.
- Single config file `config/http-logger.php` (publishable) with env support.
- Dedicated log channel and customizable message prefix (`[HttpLogger]`).
- Full test coverage (unit, feature, integration).
