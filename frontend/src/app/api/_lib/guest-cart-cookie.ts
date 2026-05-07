import 'server-only';

import type { NextRequest, NextResponse } from 'next/server';

/**
 * ADR-017 — Guest cart cookie BFF mediation.
 *
 * Browser ↔ BFF (same origin) ↔ Laravel (cross origin). Cookie jar
 * paylaşılmadığından her iki yönde manuel forward edilir:
 *
 * - request-out: browser cookie'sindeki guest_cart_token Laravel'e
 *   Cookie header olarak gider (forwardGuestCookie).
 * - response-in: Laravel'in Set-Cookie: guest_cart_token=… header'ı
 *   Next response'a yeniden basılır (forwardGuestSetCookie). Laravel
 *   secure flag'ini production'da true gönderir; lokalde browser
 *   secure cookie'yi reddedebileceğinden BFF flag'i kendi env'ine göre
 *   ayarlar.
 */

export const GUEST_CART_COOKIE = 'guest_cart_token';

const GUEST_CART_TTL_DAYS = 30;
const GUEST_CART_MAX_AGE_SECONDS = GUEST_CART_TTL_DAYS * 24 * 60 * 60;

/**
 * Browser → BFF cookie'sinden guest_cart_token'ı çıkar; Laravel'e
 * gönderilecek "name=value" formatlı Cookie header parçası döner.
 * Cookie yoksa undefined.
 */
export function forwardGuestCookie(request: NextRequest): string | undefined {
  const value = request.cookies.get(GUEST_CART_COOKIE)?.value;
  if (value === undefined || value === '') return undefined;
  return `${GUEST_CART_COOKIE}=${value}`;
}

/**
 * Laravel response'unda Set-Cookie: guest_cart_token=… varsa Next
 * response'una aynısını yansıt. Birden fazla Set-Cookie'yi (token
 * üretme + clear) doğru parse edebilmek için
 * `getSetCookie()` (TC39) kullanılır.
 */
export function forwardGuestSetCookie(
  backendResponse: Response,
  nextResponse: NextResponse,
): void {
  const cookieHeaders = backendResponse.headers.getSetCookie();

  for (const raw of cookieHeaders) {
    if (!raw.toLowerCase().startsWith(`${GUEST_CART_COOKIE.toLowerCase()}=`)) {
      continue;
    }

    // Laravel ham Set-Cookie değerini gönderiyor — direkt forward
    // etmek mümkün ama secure flag development HTTP'sinde sorun
    // çıkarıyor. Cookie name=value parse edip kendi flag'lerimizle
    // yeniden inşa ediyoruz.
    const eq = raw.indexOf('=');
    if (eq === -1) continue;
    const semi = raw.indexOf(';', eq);
    const value = semi === -1 ? raw.slice(eq + 1) : raw.slice(eq + 1, semi);

    nextResponse.cookies.set({
      name: GUEST_CART_COOKIE,
      value,
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'strict',
      path: '/',
      // Boş value → Laravel cookie'yi clear etmek istiyor (login/register sonrası)
      maxAge: value === '' ? 0 : GUEST_CART_MAX_AGE_SECONDS,
    });
  }
}
