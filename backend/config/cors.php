<?php
// config/cors.php

return [
    /*
    |--------------------------------------------------------------------------
    | SECURITY: Restrict CORS to known frontend origins only.
    | In production, replace with your actual domain(s).
    | Never use '*' for allowed_origins in production.
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // SECURITY: explicit whitelist — no wildcard
    // Set APP_FRONTEND_URL in your .env file
    'allowed_origins' => [
        env('APP_FRONTEND_URL', 'http://localhost:3000'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    // Cache preflight for 2 hours
    'max_age' => 7200,

    // Required for Sanctum cookie-based auth
    'supports_credentials' => true,
];
