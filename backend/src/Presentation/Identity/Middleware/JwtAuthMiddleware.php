<?php

declare(strict_types=1);

namespace Presentation\Identity\Middleware;

use Closure;
use Domain\Identity\Contracts\JwtBlacklistInterface;
use Domain\Identity\Contracts\JwtIssuerInterface;
use Domain\Identity\Exceptions\UnauthenticatedException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the access token on protected routes and stamps the request
 * with `user_id`, `user_role`, `jwt_jti`, `jwt_exp` attributes for
 * downstream consumers (controllers via ResolvesRequestContext,
 * RoleMiddleware, logout's blacklist insert).
 *
 * Token sources, in order of preference:
 *   1. Authorization: Bearer <jwt>  (BFF forwards cookie → header)
 *   2. Cookie: access_token         (direct browser fallback, dev only)
 *
 * After signature/claims verification, the jti is checked against the
 * JwtBlacklist — a logged-out access token will be rejected before its
 * 15-minute natural expiry.
 */
final class JwtAuthMiddleware
{
    public function __construct(
        private readonly JwtIssuerInterface $jwtIssuer,
        private readonly JwtBlacklistInterface $blacklist,
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

        $jti = isset($payload['jti']) ? (string) $payload['jti'] : null;
        if ($jti !== null && $this->blacklist->contains($jti)) {
            throw UnauthenticatedException::invalidToken();
        }

        $request->attributes->set('user_id', $payload['sub']);
        $request->attributes->set('user_role', $payload['role'] ?? null);
        $request->attributes->set('jwt_jti', $jti);
        $request->attributes->set('jwt_exp', $payload['exp'] ?? null);

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
