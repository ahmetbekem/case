import 'server-only';

import { cookies } from 'next/headers';

/**
 * httpOnly cookie storage for the JWT pair (SECURITY.md §4.2).
 *
 * - access cookie path:  '/' — every API request needs it
 * - refresh cookie path: '/api/auth' — refresh is the only place the
 *   browser must send it; narrowing the path shrinks exposure
 *
 * Names start with `st_` to keep them short (compact request headers)
 * and to make them grep-able across CI logs.
 */

export const ACCESS_COOKIE = 'st_at';
export const REFRESH_COOKIE = 'st_rt';

const ACCESS_MAX_AGE_SECONDS_DEFAULT = 15 * 60; // 15 min
const REFRESH_MAX_AGE_SECONDS = 7 * 24 * 60 * 60; // 7 day

const cookieBaseOptions = () => ({
  httpOnly: true,
  secure: process.env.NODE_ENV === 'production',
  sameSite: 'strict' as const,
  path: '/',
});

export async function readAccessToken(): Promise<string | undefined> {
  const jar = await cookies();
  return jar.get(ACCESS_COOKIE)?.value;
}

export async function readRefreshToken(): Promise<string | undefined> {
  const jar = await cookies();
  return jar.get(REFRESH_COOKIE)?.value;
}

export async function setAuthCookies(
  accessToken: string,
  refreshToken: string,
  accessMaxAgeSeconds: number = ACCESS_MAX_AGE_SECONDS_DEFAULT,
): Promise<void> {
  const jar = await cookies();
  jar.set(ACCESS_COOKIE, accessToken, {
    ...cookieBaseOptions(),
    maxAge: accessMaxAgeSeconds,
  });
  jar.set(REFRESH_COOKIE, refreshToken, {
    ...cookieBaseOptions(),
    path: '/api/auth',
    maxAge: REFRESH_MAX_AGE_SECONDS,
  });
}

export async function clearAuthCookies(): Promise<void> {
  const jar = await cookies();
  jar.set(ACCESS_COOKIE, '', { ...cookieBaseOptions(), maxAge: 0 });
  jar.set(REFRESH_COOKIE, '', {
    ...cookieBaseOptions(),
    path: '/api/auth',
    maxAge: 0,
  });
}
