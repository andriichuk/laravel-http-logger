<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Log HTTP Requests
    |--------------------------------------------------------------------------
    |
    | This option controls whether HTTP requests should be logged.
    | Use LOG_HTTP_REQUESTS in .env to enable or disable explicitly.
    | When LOG_HTTP_REQUESTS is not set, logging follows APP_DEBUG (enabled
    | in debug mode, disabled in production).
    |
    */

    'enabled' => env('LOG_HTTP_REQUESTS', (bool) env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel to use. Ensure this channel exists in config/logging.php.
    | Defaults to HTTP_LOG_CHANNEL, or LOG_CHANNEL, or "daily" if unset.
    |
    */

    'channel' => env('HTTP_LOG_CHANNEL', env('LOG_CHANNEL', 'daily')),

    /*
    |--------------------------------------------------------------------------
    | Route Patterns
    |--------------------------------------------------------------------------
    |
    | Laravel route patterns to log. ['*'] = all routes. Use [] for none.
    | Or restrict to patterns e.g. ['api/*', 'webhook/*'].
    |
    */

    'routes' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Report by Status
    |--------------------------------------------------------------------------
    |
    | Which response status codes to log: 1xx info, 2xx success, 3xx redirect,
    | 4xx client_error, 5xx server_error. Set to true to log that category.
    |
    */

    'report' => [
        'info' => false,
        'success' => false,
        'redirect' => true,
        'client_error' => true,
        'server_error' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Level by Response Status
    |--------------------------------------------------------------------------
    |
    | Map each response status category to a PSR log level. Used so 4xx/5xx
    | can be logged as warning/error for easier filtering. Keys match 'report':
    | info, success, redirect, client_error, server_error. Levels: debug, info,
    | notice, warning, error, critical, alert, emergency.
    |
    */

    'log_level_by_status' => [
        'info' => 'info',
        'success' => 'info',
        'redirect' => 'info',
        'client_error' => 'warning',
        'server_error' => 'error',
    ],

    'include_response' => true,

    /*
    |--------------------------------------------------------------------------
    | Include Non-JSON Response Body (HTML, text, etc.)
    |--------------------------------------------------------------------------
    |
    | When include_response is true, JSON responses are always decoded and
    | sanitized. When this option is true, non-JSON responses (e.g. HTML or
    | plain text) are also included in the log (truncated by max_string_value_length).
    | Default false logs non-JSON response body as '[skipped]'.
    |
    */

    'include_non_json_response' => false,

    /*
    |--------------------------------------------------------------------------
    | Request Headers to Include
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to include in the log context for the request.
    | Use ['*'] to include all request headers.
    |
    */

    'include_request_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Response Headers to Include
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to include in the log context for the response.
    | Use ['*'] to include all response headers.
    |
    */

    'include_response_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Body Fields
    |--------------------------------------------------------------------------
    |
    | Request/response body keys to replace with *** in logs (e.g. token, password).
    |
    */

    'sensitive_fields' => [
        'token',
        '_token',
        'refresh_token',
        'password',
        'confirm_password',
        'access_token',
        'api_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Headers
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to replace with *** in logs (e.g. authorization, cookie).
    |
    */

    'sensitive_headers' => [
        'authorization',
        'cookie',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max String Value Length
    |--------------------------------------------------------------------------
    |
    | Maximum length for string values in request/response bodies before
    | truncation (ellipsis added). Also used to truncate non-JSON response
    | bodies (e.g. HTML or plain text) when logged. Set to null to disable
    | truncation (log full length).
    |
    */

    'max_string_value_length' => 100,

    'message_prefix' => '[HttpLogger] ',

    /*
    |--------------------------------------------------------------------------
    | Include Host in Log Message
    |--------------------------------------------------------------------------
    |
    | When true, the log message will include the request URL origin with protocol
    | (e.g. https://example.com). Format: prefix + method + schemeAndHost + path.
    |
    */

    'include_host_in_message' => false,

    /*
    |--------------------------------------------------------------------------
    | Include Session Errors in Log Context
    |--------------------------------------------------------------------------
    |
    | When true, and the request has a session with flashed validation errors
    | (e.g. from redirect()->back()->withErrors()), add a "session_errors"
    | key to the log context. Errors are read only and remain available for
    | the client on the next request.
    |
    */

    'include_session_errors' => false,

    /*
    |--------------------------------------------------------------------------
    | Include Uploaded Files Metadata
    |--------------------------------------------------------------------------
    |
    | When true, requests that contain file uploads will have uploaded file
    | metadata (original name, size, MIME type, extension) added to the log
    | context under the "uploaded_files" key. No file contents are logged.
    |
    */

    'include_uploaded_files_metadata' => true,
];
