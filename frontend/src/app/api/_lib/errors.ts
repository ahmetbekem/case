import { NextResponse } from 'next/server';

import { BackendError } from '@/shared/api/server-fetch';

/**
 * Coerce any thrown value into a NextResponse with the right shape.
 *
 * - BackendError: forward Laravel's envelope + status code unchanged so
 *   the client always sees the same `{ error: { code, message } }` shape
 *   it would have got via the catch-all proxy.
 * - Anything else: 500 with a generic envelope.
 */
export function errorToResponse(error: unknown): NextResponse {
  if (error instanceof BackendError) {
    const body =
      error.body && typeof error.body === 'object'
        ? error.body
        : { error: { code: 'BACKEND_ERROR', message: error.message } };
    return NextResponse.json(body, { status: error.status });
  }

  return NextResponse.json(
    {
      error: {
        code: 'PROXY_ERROR',
        message: 'Internal proxy error',
      },
    },
    { status: 500 },
  );
}

export function correlationIdFrom(headers: Headers): string | undefined {
  return headers.get('x-correlation-id') ?? undefined;
}
