import { NextRequest, NextResponse } from 'next/server';

import { backendJson } from '@/shared/api/server-fetch';

import { setAuthCookies } from '../../_lib/cookies';
import { correlationIdFrom, errorToResponse } from '../../_lib/errors';
import { GUEST_CART_COOKIE, forwardGuestCookie } from '../../_lib/guest-cart-cookie';

interface LoginBackendResponse {
  data: {
    user: Record<string, unknown>;
    accessToken: string;
    accessTokenExpiresIn: number;
    refreshToken: string;
    tokenType: string;
    /** ADR-017 — login sırasında guest cart user cart ile birleştirildi mi? */
    cartMerged: boolean;
  };
  message?: string;
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    // ADR-017 — guest cookie varsa Laravel'e forward et; backend
    // MergeGuestCartCommand'ı tetikler.
    const guestCookie = forwardGuestCookie(request);

    const data = await backendJson<LoginBackendResponse>('/auth/login', {
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

    // Strip raw tokens from the body — they live in httpOnly cookies now.
    const response = NextResponse.json({
      data: { user: data.data.user, cartMerged: data.data.cartMerged },
      message: data.message,
    });

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
