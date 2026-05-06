<?php

declare(strict_types=1);

namespace Presentation\Sales\Middleware;

use Closure;
use Domain\Sales\ValueObjects\GuestSessionId;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * ADR-017 Guest cart cookie middleware.
 *
 * Davranış:
 *   - request user_id attribute taşıyorsa (OptionalJwtMiddleware set
 *     ettiyse) → no-op, mevcut guest cookie'ye dokunma.
 *   - guest_cart_token cookie zaten varsa → attribute'a koy, geç.
 *   - Cookie yoksa → yeni UUID v4 üret, response cookie'sine yaz, aynı
 *     ID'yi attribute'a koy ki controller hemen kullanabilsin.
 *
 * Cookie güvenliği: httpOnly (XSS koruması), Secure (HTTPS-only),
 * SameSite=Strict (CSRF koruması), 30 gün TTL.
 */
final class GuestSessionMiddleware
{
    public const string COOKIE_NAME = 'guest_cart_token';

    /** Cart::GUEST_CART_TTL_DAYS ile aynı süre — cookie ve DB lifecycle senkron. */
    public const int COOKIE_MAX_AGE_SECONDS = 30 * 24 * 60 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        // Authenticated user — guest cookie üretmeye gerek yok
        if ($request->attributes->has('user_id')) {
            return $next($request);
        }

        $existing = $request->cookie(self::COOKIE_NAME);
        if (is_string($existing) && $this->looksLikeUuid($existing)) {
            $request->attributes->set('guest_session_id', $existing);
            return $next($request);
        }

        $newSessionId = GuestSessionId::generate()->value();
        $request->attributes->set('guest_session_id', $newSessionId);

        $response = $next($request);

        $cookie = Cookie::create(
            name: self::COOKIE_NAME,
            value: $newSessionId,
            expire: time() + self::COOKIE_MAX_AGE_SECONDS,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT,
        );
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Cookie'deki değer UUID v4 mü? Bot/spam veya bozuk cookie geldiyse
     * GuestSessionId::fromString patlamasın diye yumuşak filter.
     */
    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        );
    }
}
