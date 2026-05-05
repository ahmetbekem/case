<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        parent::setUp();

        // Cache::flush wipes the array-driver cache — rate-limiter buckets,
        // JWT blacklist, currency cache — so each test starts on a clean
        // slate. Without this, sequential tests share the throttle:order
        // bucket (Pest runs in one PHP process; array cache survives).
        Cache::flush();

        // Default headers for every Feature HTTP test:
        // - X-Proxy-Secret satisfies ProxyOriginMiddleware
        // - Origin satisfies CsrfOriginMiddleware on POST/PUT/PATCH/DELETE
        // Specific tests can drop these via $this->withoutHeaders([...]).
        $this->withHeaders([
            'X-Proxy-Secret' => (string) config('security.proxy_secret', ''),
            'Origin' => 'http://localhost',
        ]);
    }
}
