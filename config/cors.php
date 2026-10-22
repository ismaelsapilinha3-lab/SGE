<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200,http://127.0.0.1:4200')),

    /*
    |--------------------------------------------------------------------------
    | Origens por expressão regular
    |--------------------------------------------------------------------------
    |
    | Por omissão aceita qualquer porta local (localhost / 127.0.0.1), o que
    | permite ao dev server do Angular usar portas efémeras (ex.: 62060).
    | Qualquer outra origem tem de ser listada em CORS_ALLOWED_ORIGINS.
    |
    */

    'allowed_origins_patterns' => array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_ORIGIN_PATTERNS',
        '{^https?://(localhost|127\.0\.0\.1)(:\d+)?$}'
    )))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
