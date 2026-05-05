import { NextRequest, NextResponse } from 'next/server';

import { backendJson } from '@/shared/api/server-fetch';

import { setAuthCookies } from '../../_lib/cookies';
import { correlationIdFrom, errorToResponse } from '../../_lib/errors';

interface RegisterBackendResponse {
  data: {
    user: Record<string, unknown>;
    accessToken: string;
    accessTokenExpiresIn: number;
    refreshToken: string;
    tokenType: string;
  };
  message?: string;
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const data = await backendJson<RegisterBackendResponse>('/auth/register', {
      method: 'POST',
      body,
      correlationId: correlationIdFrom(request.headers),
    });

    await setAuthCookies(
      data.data.accessToken,
      data.data.refreshToken,
      data.data.accessTokenExpiresIn,
    );

    return NextResponse.json(
      {
        data: { user: data.data.user },
        message: data.message,
      },
      { status: 201 },
    );
  } catch (error) {
    return errorToResponse(error);
  }
}
