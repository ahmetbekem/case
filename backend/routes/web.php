<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Detailed health check — DB + cache reachable + writable.
 *
 * Distinct from `/up` (Laravel's withRouting basic 200 — used as the
 * Dockerfile HEALTHCHECK and as a k8s liveness probe). `/health` is
 * the richer one a monitoring tool / k8s readiness probe should hit:
 * any failed check returns 503 with detail per dependency.
 */
Route::get('/health', function () {
    $checks = [
        'app' => 'ok',
        'database' => null,
        'cache' => null,
    ];
    $status = 200;

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Throwable $e) {
        $checks['database'] = 'down';
        $status = 503;
    }

    try {
        $probeKey = '__health_probe__';
        Cache::put($probeKey, '1', 1);
        $checks['cache'] = Cache::get($probeKey) === '1' ? 'ok' : 'degraded';
        if ($checks['cache'] !== 'ok') {
            $status = 503;
        }
    } catch (\Throwable $e) {
        $checks['cache'] = 'down';
        $status = 503;
    }

    return response()->json($checks, $status);
});
