import { NextRequest, NextResponse } from 'next/server';

import { backendFetch } from '@/shared/api/server-fetch';

import {
  clearAuthCookies,
  readAccessToken,
  readRefreshToken,
} from '../../_lib/cookies';
import { correlationIdFrom } from '../../_lib/errors';

/**
 * Best-effort logout: forward to Laravel so the refresh-token family
 * gets revoked + the access-token jti is blacklisted, then clear the
 * cookies regardless of the backend result. Even a backend outage
 * mustn't trap the browser in a logged-in state.
 */
export async function POST(request: NextRequest) {
  const access = await readAccessToken();
  const refresh = await readRefreshToken();

  if (access !== undefined && refresh !== undefined) {
    try {
      await backendFetch('/auth/logout', {
        method: 'POST',
        body: { refresh_token: refresh },
        bearer: access,
        correlationId: correlationIdFrom(request.headers),
      });
    } catch {
      // ignore — clearing the cookies is the source of truth
    }
  }

  await clearAuthCookies();
  return new NextResponse(null, { status: 204 });
}
