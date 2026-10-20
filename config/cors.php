<?php

/*
|--------------------------------------------------------------------------
| Cross-origin requests
|--------------------------------------------------------------------------
| The storefront SPA (electronic-store-web) is served from its own origin and
| calls /api/v1 with a bearer token — no cookies, so credentials stay off.
| STOREFRONT_URL takes a comma-separated list (dev server, staging, prod).
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('STOREFRONT_URL', 'http://localhost:5173')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Accept-Language', 'Authorization', 'Content-Type', 'Idempotency-Key', 'X-Cart-Token', 'X-Requested-With'],

    // Retry-After lets the SPA wait out a 429 instead of showing a dead error.
    'exposed_headers' => ['Content-Language', 'Retry-After', 'X-RateLimit-Remaining'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
