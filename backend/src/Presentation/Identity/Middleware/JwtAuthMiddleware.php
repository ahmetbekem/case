<?php

declare(strict_types=1);

namespace Presentation\Identity\Middleware;

use Closure;
use Domain\Identity\Contracts\JwtIssuerInterface;
use Domain\Identity\Exceptions\UnauthenticatedException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the access token on protected routes and stamps the request
 * with `user_id`, `user_role`, and `jwt_jti` attributes for downstream
 * consumers (controllers via ResolvesRequestContext, RoleMiddleware in
 * TASK 0.8, blacklist check in the same task).
 *
 * Token sources, in order of preference:
 *   1. Authorization: Bearer <jwt>  (BFF forwards cookie → header)
 *   2. Cookie: access_token         (direct browser fallback, dev only)
 */
final class JwtAuthMiddleware
{
    public function __construct(
        private readonly JwtIssuerInterface $jwtIssuer,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null || $token === '') {
            throw UnauthenticatedException::missingToken();
        }

        $payload = $this->jwtIssuer->verify($token);
        if ($payload === null) {
            throw UnauthenticatedException::invalidToken();
        }

        $request->attributes->set('user_id', $payload['sub']);
        $request->attributes->set('user_role', $payload['role'] ?? null);
        $request->attributes->set('jwt_jti', $payload['jti'] ?? null);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $auth = $request->header('Authorization');
        if (is_string($auth) && str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        return $request->cookie('access_token');
    }
}
