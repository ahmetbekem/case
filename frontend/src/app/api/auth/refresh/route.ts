import { NextResponse } from 'next/server';

import { tryRefresh } from '../../_lib/refresh';

/**
 * Browser-callable refresh endpoint. Reads st_rt cookie, calls Laravel,
 * rotates cookies. The new tokens are NOT echoed in the body —
 * httpOnly cookies are the only carrier.
 */
export async function POST() {
  const result = await tryRefresh();

  if (!result.ok) {
    return NextResponse.json(
      {
        error: {
          code: 'REFRESH_FAILED',
          message: 'Could not refresh session',
        },
      },
      { status: 401 },
    );
  }

  return NextResponse.json({ data: { ok: true } });
}
