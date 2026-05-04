<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Suppress PHP 8.5 deprecation warnings from Laravel 11's MySQL
        // config so Pest does not flag every test as `DEPR`. Same rationale
        // as the bootstrap/app.php filter — temporary until upstream patch.
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        parent::setUp();
    }
}
