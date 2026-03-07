<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Log HTTP Requests
    |--------------------------------------------------------------------------
    |
    | This option controls whether HTTP requests should be logged.
    | Set to true to enable, or use LOG_HTTP_REQUESTS env variable.
    |
    */

    'enabled' => env('LOG_HTTP_REQUESTS', false),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel to use. Ensure this channel exists in config/logging.php.
    | Example: add a stack or single channel named "http" in logging.php.
    |
    */

    'channel' => env('HTTP_LOG_CHANNEL', 'http'),

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
        'redirect' => false,
        'client_error' => true,
        'server_error' => true,
    ],

    'include_response' => true,

    /*
    |--------------------------------------------------------------------------
    | Request Headers to Include
    |--------------------------------------------------------------------------
    |
    | Header names (lowercase) to include in the log context for the request.
    | Use ['*'] to include all request headers.
    |
    */

    'include_request_headers' => [
        'x-app-version',
        'x-device-id',
        'x-device-type',
    ],

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
    | Max Body Length
    |--------------------------------------------------------------------------
    |
    | Maximum string length for body values before truncation (ellipsis added).
    |
    */

    'max_body_length' => 100,

    'message_prefix' => '[HttpLogger] ',
];
