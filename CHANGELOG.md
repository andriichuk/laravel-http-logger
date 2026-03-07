# Changelog

All notable changes to `laravel-http-logger` will be documented in this file.

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
