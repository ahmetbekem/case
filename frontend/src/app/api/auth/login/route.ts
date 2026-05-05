import { NextRequest, NextResponse } from 'next/server';

import { backendJson } from '@/shared/api/server-fetch';

import { setAuthCookies } from '../../_lib/cookies';
import { correlationIdFrom, errorToResponse } from '../../_lib/errors';

interface LoginBackendResponse {
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
    const data = await backendJson<LoginBackendResponse>('/auth/login', {
      method: 'POST',
      body,
      correlationId: correlationIdFrom(request.headers),
    });

    await setAuthCookies(
      data.data.accessToken,
      data.data.refreshToken,
      data.data.accessTokenExpiresIn,
    );

    // Strip raw tokens from the body — they live in httpOnly cookies now.
    return NextResponse.json({
      data: { user: data.data.user },
      message: data.message,
    });
  } catch (error) {
    return errorToResponse(error);
  }
}
