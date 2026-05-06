<?php

declare(strict_types=1);

namespace Presentation\Identity\Middleware;

use Closure;
use Domain\Identity\Contracts\JwtBlacklistInterface;
use Domain\Identity\Contracts\JwtIssuerInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-017 — JwtAuthMiddleware'in "auth ZORUNLU değil" varyantı. Cart
 * endpoint'leri kullanır: token varsa user_id attribute set edilir,
 * yoksa veya geçersizse sessizce devam eder (guest cart akışına izin
 * verilsin diye). 401 fırlatmaz.
 *
 * Bu middleware GuestSessionMiddleware'den ÖNCE çalışmalı — guest
 * session middleware request'in user_id taşıyıp taşımadığına bakarak
 * cookie üretip üretmeyeceğine karar veriyor.
 */
final class OptionalJwtMiddleware
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
            return $next($request);
        }

        $payload = $this->jwtIssuer->verify($token);
        if ($payload === null) {
            return $next($request);
        }

        $jti = isset($payload['jti']) ? (string) $payload['jti'] : null;
        if ($jti !== null && $this->blacklist->contains($jti)) {
            return $next($request);
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
