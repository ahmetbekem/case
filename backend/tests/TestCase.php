<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        parent::setUp();

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
