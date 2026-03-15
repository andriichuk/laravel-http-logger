# Changelog

All notable changes to `laravel-http-logger` will be documented in this file.

## 0.2.6 - 2025-03-15

### Changed

- **Log context keys renamed (breaking):** `status_code` → `response_status_code`, `request` → `request_body`, `response` → `response_body`. Update log consumers and any assertions that rely on these keys.
- When `include_host_in_message` is enabled, the log message no longer has a trailing space between the host and the path (e.g. `GET https://example.com/users` instead of `GET https://example.com /users`).

## 0.2.2 - 2025-03-08

### Added

- **`response_status_code`** — HTTP response status code is now always included in the log context.
- **`log_level_by_status`** — Config option to map response status categories to PSR log levels (e.g. 5xx → `error`, 4xx → `warning`). Enables filtering and alerting by level in log aggregation.

### Changed

- Logging now uses `Log::log($level, $message, $context)` instead of always `info`. Default mapping: 1xx/2xx/3xx → `info`, 4xx → `warning`, 5xx → `error`.

## 0.2.1 - 2025-03-08

### Fixed

- Config table header in README (missing Key/Description columns).

## 0.2.0 - 2025-03-08

### Added

- **File upload metadata** — Option `include_uploaded_files_metadata` (default `true`) to log uploaded file metadata (name, original_name, size, mime_type, extension, error) in context under `uploaded_files`. No file contents are logged.
- **APP_DEBUG fallback** — When `LOG_HTTP_REQUESTS` is not set, logging is enabled when `APP_DEBUG=true` and disabled when `APP_DEBUG=false`.

### Changed

- Request body passed to the sanitizer now excludes file inputs (query + body + attributes only) to avoid issues with `UploadedFile` instances.
- Sanitizer treats object values as `[object]` instead of casting to array (avoids recursion on file objects).

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
