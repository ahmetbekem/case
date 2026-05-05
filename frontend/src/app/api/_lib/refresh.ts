import 'server-only';

import { backendJson, BackendError } from '@/shared/api/server-fetch';

import {
  clearAuthCookies,
  readRefreshToken,
  setAuthCookies,
} from './cookies';

interface BackendRefreshResponse {
  data: {
    accessToken: string;
    accessTokenExpiresIn: number;
    refreshToken: string;
    tokenType: string;
  };
}

export type RefreshResult =
  | { ok: true; accessToken: string }
  | { ok: false; reason: 'no-refresh-cookie' | 'backend-rejected' };

/**
 * Reads the refresh cookie, calls Laravel /auth/refresh, rotates the
 * cookies on success, clears them on failure. Used by the catch-all
 * proxy on a 401 retry path AND by the /api/auth/refresh route handler.
 */
export async function tryRefresh(): Promise<RefreshResult> {
  const refreshToken = await readRefreshToken();
  if (refreshToken === undefined || refreshToken === '') {
    return { ok: false, reason: 'no-refresh-cookie' };
  }

  try {
    const data = await backendJson<BackendRefreshResponse>('/auth/refresh', {
      method: 'POST',
      body: { refresh_token: refreshToken },
    });

    await setAuthCookies(
      data.data.accessToken,
      data.data.refreshToken,
      data.data.accessTokenExpiresIn,
    );

    return { ok: true, accessToken: data.data.accessToken };
  } catch (error) {
    // Replay detection (REFRESH_TOKEN_REPLAY) or expired/invalid token —
    // always nuke local cookies so the browser doesn't keep retrying.
    if (error instanceof BackendError) {
      await clearAuthCookies();
    }
    return { ok: false, reason: 'backend-rejected' };
  }
}
