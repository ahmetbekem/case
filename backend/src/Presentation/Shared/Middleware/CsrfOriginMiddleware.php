<?php

declare(strict_types=1);

namespace Presentation\Shared\Middleware;

use Closure;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Security\Exceptions\CsrfOriginException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth CSRF mitigation on top of SameSite=Strict cookies:
 * every state-changing request must carry an Origin (or Referer) header
 * pointing to one of the trusted client origins.
 *
 * SameSite=Strict already blocks ~95% of CSRF, but Origin validation
 * also catches CSRF from malicious extensions, native apps with stale
 * cookies, and a few exotic redirect-based attacks (SECURITY.md §4.5).
 */
final class CsrfOriginMiddleware
{
    private const array UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly AuditLoggerInterface $auditLogger,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->getMethod(), self::UNSAFE_METHODS, true)) {
            return $next($request);
        }

        $origin = $request->header('Origin') ?? $request->header('Referer');

        if ($origin === null || $origin === '') {
            $this->emitFailureAudit($request, 'missing_origin', null);
            throw CsrfOriginException::missingOrigin();
        }

        /** @var array<int, string> $allowed */
        $allowed = (array) config('security.allowed_origins', []);

        $matched = false;
        foreach ($allowed as $allowedOrigin) {
            if ($allowedOrigin === '') {
                continue;
            }
            if (str_starts_with($origin, $allowedOrigin)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $this->emitFailureAudit($request, 'invalid_origin', $origin);
            throw CsrfOriginException::invalidOrigin($origin);
        }

        return $next($request);
    }

    private function emitFailureAudit(Request $request, string $reason, ?string $origin): void
    {
        $this->auditLogger->log(new AuditEvent(
            actor: (string) ($request->attributes->get('user_id') ?? 'anonymous'),
            actorIp: $request->ip() ?? 'unknown',
            action: 'security.csrf_origin_mismatch',
            resource: $request->getPathInfo(),
            module: 'security',
            result: 'failure',
            correlationId: (string) ($request->attributes->get('correlation_id') ?? 'no-correlation'),
            userAgent: $request->userAgent(),
            metadata: [
                'reason' => $reason,
                'origin' => $origin,
                'method' => $request->getMethod(),
            ],
        ));
    }
}
