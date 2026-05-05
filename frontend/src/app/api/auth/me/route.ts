import { NextRequest, NextResponse } from 'next/server';

import { backendJson } from '@/shared/api/server-fetch';

import { readAccessToken } from '../../_lib/cookies';
import { correlationIdFrom, errorToResponse } from '../../_lib/errors';
import { tryRefresh } from '../../_lib/refresh';

/**
 * GET /api/auth/me — returns the current user, transparently refreshing
 * once if the access token is missing or rejected as invalid.
 */
export async function GET(request: NextRequest) {
  let access = await readAccessToken();

  if (access === undefined) {
    const refreshed = await tryRefresh();
    if (!refreshed.ok) {
      return NextResponse.json(
        {
          error: {
            code: 'UNAUTHENTICATED',
            message: 'Login required',
          },
        },
        { status: 401 },
      );
    }
    access = refreshed.accessToken;
  }

  try {
    const data = await backendJson('/auth/me', {
      bearer: access,
      correlationId: correlationIdFrom(request.headers),
    });
    return NextResponse.json(data);
  } catch (error) {
    return errorToResponse(error);
  }
}
