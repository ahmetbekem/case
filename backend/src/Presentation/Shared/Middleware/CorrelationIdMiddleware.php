<?php

declare(strict_types=1);

namespace Presentation\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * Threads a stable correlation id through the entire request lifecycle.
 * If the BFF (or any caller) sends X-Correlation-Id, we honour it;
 * otherwise we mint a fresh v4 UUID. Either way the value lands on the
 * request's attribute bag (so handlers/audit can pick it up) and is
 * mirrored back on the response so clients can stitch logs.
 */
final class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Correlation-Id');
        $cid = is_string($incoming) && $incoming !== ''
            ? $this->sanitize($incoming)
            : Uuid::uuid4()->toString();

        $request->attributes->set('correlation_id', $cid);

        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $cid);

        return $response;
    }

    private function sanitize(string $value): string
    {
        // Limit to 128 chars + ASCII printable to keep log fields predictable
        $value = substr($value, 0, 128);
        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    }
}
