<?php

declare(strict_types=1);

namespace Presentation\Shared\Middleware;

use Closure;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Security\Exceptions\RoleDeniedException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RBAC gate. Reads the user_role attribute set by JwtAuthMiddleware and
 * compares it to the comma list passed as middleware parameters:
 *   Route::middleware('role:admin')
 *   Route::middleware('role:admin,operator')
 */
final class RoleMiddleware
{
    public function __construct(
        private readonly AuditLoggerInterface $auditLogger,
    ) {
    }

    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $userRole = $request->attributes->get('user_role');
        $userId = (string) ($request->attributes->get('user_id') ?? 'anonymous');

        if (!is_string($userRole) || !in_array($userRole, $allowedRoles, true)) {
            $this->auditLogger->log(new AuditEvent(
                actor: 'userId:' . $userId,
                actorIp: $request->ip() ?? 'unknown',
                action: 'security.role_denied',
                resource: $request->getPathInfo(),
                module: 'security',
                result: 'failure',
                correlationId: (string) ($request->attributes->get('correlation_id') ?? 'no-correlation'),
                userAgent: $request->userAgent(),
                metadata: [
                    'userRole' => $userRole,
                    'requiredRoles' => $allowedRoles,
                    'method' => $request->getMethod(),
                ],
            ));

            throw RoleDeniedException::forUser($userId, is_string($userRole) ? $userRole : null, $allowedRoles);
        }

        return $next($request);
    }
}
