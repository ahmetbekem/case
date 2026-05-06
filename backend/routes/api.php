<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Each module owns a separate file under routes/api/. The TASK 0.8 work
| will wrap this group with the proxy.origin + correlation.id middleware
| pair so direct backend traffic is rejected at the edge.
|
*/

// Laravel 11's withRouting(api: ...) auto-prefixes 'api' — we add 'v1'
// here so the effective path is /api/v1/...
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/identity.php';
    require __DIR__ . '/api/catalog.php';
    require __DIR__ . '/api/currency.php';
    require __DIR__ . '/api/sales.php';
    require __DIR__ . '/api/system.php';
});
