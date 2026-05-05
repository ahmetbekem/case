'use client';

import type { ApiErrorEnvelope } from './types';

/**
 * Browser-side fetch helper. ALWAYS hits same-origin /api/* (the BFF
 * proxy in src/app/api). NEVER touches Laravel directly — that would
 * require the backend secret + a permissive CORS config and would
 * defeat the whole point of the proxy layer (SECURITY.md §3.2).
 */

export class ClientApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly context?: Record<string, unknown>,
  ) {
    super(message);
    this.name = 'ClientApiError';
  }
}

export interface ClientFetchOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
}

export async function clientFetch<T = unknown>(
  path: string,
  options: ClientFetchOptions = {},
): Promise<T> {
  const { body, headers, ...rest } = options;

  const requestHeaders = new Headers(headers);
  requestHeaders.set('Accept', 'application/json');
  if (body !== undefined && !requestHeaders.has('Content-Type')) {
    requestHeaders.set('Content-Type', 'application/json');
  }

  const url = path.startsWith('/') ? path : `/${path}`;

  const response = await fetch(url, {
    ...rest,
    headers: requestHeaders,
    body: body === undefined ? undefined : JSON.stringify(body),
    credentials: 'same-origin',
  });

  const text = await response.text();
  let data: unknown = null;
  if (text !== '') {
    try {
      data = JSON.parse(text);
    } catch {
      throw new ClientApiError(
        response.status,
        'INVALID_JSON',
        'Server response was not valid JSON',
      );
    }
  }

  if (!response.ok) {
    const envelope = data as ApiErrorEnvelope | null;
    throw new ClientApiError(
      response.status,
      envelope?.error?.code ?? 'UNKNOWN_ERROR',
      envelope?.error?.message ?? 'Request failed',
      envelope?.error?.context,
    );
  }

  return data as T;
}
