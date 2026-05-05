<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Access;

use Closure;
use Illuminate\Http\Request;
use Infrastructure\Logging\Publishing\QueuedLogPublisher;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures one access log per HTTP request, dispatched after the
 * response has been sent so the user never waits for log shipping
 * (Laravel's terminate() hook fires post-response).
 *
 * Wired into the global API middleware stack in bootstrap/app.php
 * (after CorrelationId / ProxyOrigin / Csrf).
 */
final class AccessLogMiddleware
{
    public function __construct(
        private readonly QueuedLogPublisher $publisher,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $request->attributes->set(
            'access_log_duration_ms',
            (int) ((microtime(true) - $start) * 1000),
        );
        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $envelope = [
            'id' => Uuid::uuid4()->toString(),
            'category' => 'access',
            'publishedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            'source' => 'laravel-api',
            'version' => '1.0',
            'payload' => [
                'method' => $request->getMethod(),
                'path' => $request->getPathInfo(),
                'statusCode' => $response->getStatusCode(),
                'durationMs' => (int) ($request->attributes->get('access_log_duration_ms') ?? 0),
                'userId' => $request->attributes->get('user_id'),
                'ip' => $request->ip(),
                'userAgent' => $request->userAgent(),
                'correlationId' => $request->attributes->get('correlation_id'),
                'requestSize' => (int) ($request->headers->get('Content-Length', '0') ?: 0),
                'responseSize' => strlen((string) $response->getContent()),
            ],
        ];

        $this->publisher->dispatchAsync('log.access.http', $envelope);
    }
}
