<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Module-scoped migration paths. Laravel auto-loads
        // database/migrations/ but ignores its sub-directories,
        // so each module folder is registered explicitly.
        $this->loadMigrationsFrom([
            database_path('migrations/0000_setup'),
            database_path('migrations/identity'),
            database_path('migrations/catalog'),
            database_path('migrations/currency'),
            database_path('migrations/sales'),
        ]);

        $this->registerRateLimiters();
    }

    /**
     * Per-action rate-limit policies. Limits sourced from SECURITY.md §7.2
     * and config/security.php so they stay tweakable without code change.
     *
     * Usage on routes:
     *   Route::post('/login', ...)->middleware('throttle:login');
     */
    private function registerRateLimiters(): void
    {
        // login: 5 attempts / 15 min, keyed by email + IP (locks out single
        // accounts without giving an attacker a DoS vector against arbitrary
        // emails — SECURITY.md §7.4)
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            $key = $email !== '' ? $email . '|' . $request->ip() : (string) $request->ip();
            return [Limit::perMinutes(15, 5)->by($key)];
        });

        RateLimiter::for('register', function (Request $request) {
            // Testing: bypass — Pest fixtures call /register many times per
            // run from the same loopback IP. Production: SECURITY.md §7.2.
            if ($this->app->environment('testing')) {
                return Limit::perMinute(1000)->by('test');
            }
            return [Limit::perMinutes(60, 3)->by((string) $request->ip())];
        });

        RateLimiter::for('refresh', function (Request $request) {
            $userId = (string) ($request->attributes->get('user_id') ?? $request->ip());
            return [Limit::perMinute(10)->by($userId)];
        });

        RateLimiter::for('api-read', function (Request $request) {
            $userId = (string) ($request->attributes->get('user_id') ?? $request->ip());
            return [Limit::perMinute(200)->by($userId)];
        });

        // order: 10 placements / hour per user — guards against runaway
        // PlaceOrder spam (each call decrements stock + writes order rows).
        RateLimiter::for('order', function (Request $request) {
            // Testing: bypass — Sales suite places many orders sequentially.
            if ($this->app->environment('testing')) {
                return Limit::perMinute(1000)->by('test');
            }
            $userId = (string) ($request->attributes->get('user_id') ?? $request->ip());
            return [Limit::perMinutes(60, 10)->by($userId)];
        });
    }
}
