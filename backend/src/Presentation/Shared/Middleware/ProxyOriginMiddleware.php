<?php

declare(strict_types=1);

namespace Presentation\Shared\Middleware;

use Closure;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Security\Exceptions\ProxyBypassException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-Proxy-Secret shared secret on every API request, so the
 * backend rejects traffic that didn't come through the Next.js BFF — even
 * if a misconfigured firewall briefly exposes the backend port to the
 * outside world (defense-in-depth, ADR-014, SECURITY.md §2.2).
 *
 * Constant-time comparison via hash_equals avoids timing oracles.
 */
final class ProxyOriginMiddleware
{
    public function __construct(
        private readonly AuditLoggerInterface $auditLogger,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = (string) config('security.proxy_secret');

        if ($expectedSecret === '') {
            // Hard fail — refuse to start with no secret configured rather
            // than silently accepting every request.
            throw new \RuntimeException(
                'BACKEND_PROXY_SECRET is not configured (config/security.php → proxy_secret)',
            );
        }

        $providedSecret = (string) ($request->header('X-Proxy-Secret') ?? '');

        if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
            $this->auditLogger->log(new AuditEvent(
                actor: 'anonymous',
                actorIp: $request->ip() ?? 'unknown',
                action: 'security.proxy_bypass_attempt',
                resource: $request->getPathInfo(),
                module: 'security',
                result: 'failure',
                correlationId: (string) ($request->attributes->get('correlation_id') ?? 'no-correlation'),
                userAgent: $request->userAgent(),
                metadata: [
                    'method' => $request->getMethod(),
                    'hasHeader' => $providedSecret !== '',
                ],
            ));

            throw ProxyBypassException::attempted();
        }

        return $next($request);
    }
}
