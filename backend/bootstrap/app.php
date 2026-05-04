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
use Presentation\Identity\Middleware\JwtAuthMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Module middleware aliases. ProxyOriginMiddleware + RoleMiddleware
        // come in TASK 0.8 (Security Middleware).
        $middleware->alias([
            'jwt.auth' => JwtAuthMiddleware::class,
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
