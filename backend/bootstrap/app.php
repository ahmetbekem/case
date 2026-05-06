<?php

// PHP 8.5 deprecates several PDO::MYSQL_ATTR_* constants used by Laravel 11's
// stock config/database.php and the framework's vendor copy. They are non-
// fatal and will be cleared in a future Laravel patch — suppress them here
// so they cannot leak into API response bodies via display_errors.
// REMOVE THIS LINE once Laravel ships a PHP 8.5-clean release.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use Domain\Shared\Exceptions\DomainException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Infrastructure\Currency\Console\SyncCurrencyRatesArtisanCommand;
use Infrastructure\Logging\Access\AccessLogMiddleware;
use Infrastructure\Logging\Console\ReplayAuditFallbackCommand;
use Infrastructure\Sales\Console\CleanExpiredGuestCartsCommand;
use Presentation\Identity\Middleware\JwtAuthMiddleware;
use Presentation\Identity\Middleware\OptionalJwtMiddleware;
use Presentation\Sales\Middleware\GuestSessionMiddleware;
use Presentation\Shared\Middleware\CorrelationIdMiddleware;
use Presentation\Shared\Middleware\CsrfOriginMiddleware;
use Presentation\Shared\Middleware\ProxyOriginMiddleware;
use Presentation\Shared\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ReplayAuditFallbackCommand::class,
        SyncCurrencyRatesArtisanCommand::class,
        CleanExpiredGuestCartsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Global API stack — runs on every /api/* request, in order.
        // CorrelationId first so subsequent middleware (audit logs!) can
        // attach the id to their events. ProxyOrigin next — fail fast on
        // unauthorised callers. CsrfOrigin filters itself to state-changing
        // methods only.
        $middleware->appendToGroup('api', [
            CorrelationIdMiddleware::class,
            ProxyOriginMiddleware::class,
            CsrfOriginMiddleware::class,
            // AccessLogMiddleware terminates after the response is sent
            // so log shipping never adds latency to the user-facing path.
            AccessLogMiddleware::class,
        ]);

        // Route-level aliases (used as middleware('jwt.auth'), middleware('role:admin'))
        $middleware->alias([
            'jwt.auth'      => JwtAuthMiddleware::class,
            'optional.jwt'  => OptionalJwtMiddleware::class,
            'guest.session' => GuestSessionMiddleware::class,
            'role'          => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render every DomainException subclass with our standard error
        // envelope + the HTTP status the exception itself declares.
        $exceptions->render(function (DomainException $e, Request $request) {
            $payload = [
                'error' => [
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                ],
            ];
            if ($e->context !== []) {
                $payload['error']['context'] = $e->context;
            }
            return response()->json($payload, $e->getHttpStatusCode());
        });
    })->create();
