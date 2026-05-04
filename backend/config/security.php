<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Backend Proxy Secret (BFF → Backend shared key)
    |--------------------------------------------------------------------------
    |
    | ProxyOriginMiddleware compares the X-Proxy-Secret header to this value
    | with hash_equals. Generate a high-entropy hex string:
    |   php -r 'echo bin2hex(random_bytes(32));'
    | Rotation: every 90 days, immediately on suspected leak (SECURITY.md §2.3).
    |
    */
    'proxy_secret' => env('BACKEND_PROXY_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Client Origins (CSRF defense)
    |--------------------------------------------------------------------------
    |
    | CsrfOriginMiddleware rejects state-changing requests whose Origin (or
    | Referer) does not start with one of these strings. Comma-separated env.
    |
    */
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('SECURITY_ALLOWED_ORIGINS', 'http://localhost:3000'))),
    )),

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Profiles
    |--------------------------------------------------------------------------
    |
    | Wired into RateLimiter::for() in AppServiceProvider::boot. Per-route
    | usage: middleware('throttle:login'), middleware('throttle:place-order'),
    | etc. Numbers come from SECURITY.md §7.2.
    |
    */
    'rate_limits' => [
        'login'      => ['attempts' => 5,   'minutes' => 15],
        'register'   => ['attempts' => 3,   'minutes' => 60],
        'refresh'    => ['attempts' => 10,  'minutes' => 1],
        'logout'     => ['attempts' => 5,   'minutes' => 1],
        'order'      => ['attempts' => 10,  'minutes' => 60],
        'admin_mut'  => ['attempts' => 50,  'minutes' => 60],
        'api_read'   => ['attempts' => 200, 'minutes' => 1],
    ],

];
