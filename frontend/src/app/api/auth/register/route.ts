import { NextRequest, NextResponse } from 'next/server';

import { backendJson } from '@/shared/api/server-fetch';

import { setAuthCookies } from '../../_lib/cookies';
import { correlationIdFrom, errorToResponse } from '../../_lib/errors';
import { GUEST_CART_COOKIE, forwardGuestCookie } from '../../_lib/guest-cart-cookie';

interface RegisterBackendResponse {
  data: {
    user: Record<string, unknown>;
    accessToken: string;
    accessTokenExpiresIn: number;
    refreshToken: string;
    tokenType: string;
    /** ADR-017 — register sırasında guest cart user'a transfer edildi mi? */
    cartMerged: boolean;
  };
  message?: string;
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const guestCookie = forwardGuestCookie(request);

    const data = await backendJson<RegisterBackendResponse>('/auth/register', {
      method: 'POST',
      body,
      correlationId: correlationIdFrom(request.headers),
      cookie: guestCookie,
    });

    await setAuthCookies(
      data.data.accessToken,
      data.data.refreshToken,
      data.data.accessTokenExpiresIn,
    );

    const response = NextResponse.json(
      {
        data: { user: data.data.user, cartMerged: data.data.cartMerged },
        message: data.message,
      },
      { status: 201 },
    );

    // ADR-017 — user mode'a geçildi; guest cart cookie'sini tara.
    if (guestCookie !== undefined) {
      response.cookies.set({
        name: GUEST_CART_COOKIE,
        value: '',
        httpOnly: true,
        secure: process.env.NODE_ENV === 'production',
        sameSite: 'strict',
        path: '/',
        maxAge: 0,
      });
    }

    return response;
  } catch (error) {
    return errorToResponse(error);
  }
}
