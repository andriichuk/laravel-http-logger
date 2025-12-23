<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Log HTTP Requests
    |--------------------------------------------------------------------------
    |
    | This option controls whether HTTP requests should be logged.
    | You can enable or disable logging based on your application's needs.
    |
    */

    'enabled' => env('LOG_HTTP_REQUESTS', false),

    'channel' => env('HTTP_LOG_CHANNEL', 'http'),

    'routes' => [

    ],

    'report' => [
        // 1xx status codes
        'info' => false,

        // 2xx status codes
        'success' => false,

        // 3xx status codes
        'redirect' => false,

        // 4xx status codes
        'client_error' => true,

        // 5xx status codes
        'server_error' => true,
    ],

    'include_response' => true,

    'include_headers' => [
        'x-app-version',
        'x-device-id',
        'x-device-type',
    ],

    'sensitive_fields' => [
        'token',
        'code',
        'refresh_token',
    ],

    'message_prefix' => 'HTTP Request: '
];
