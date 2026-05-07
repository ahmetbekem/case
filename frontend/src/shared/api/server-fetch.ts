import 'server-only';

import { serverEnv } from '@/shared/config/env';

/**
 * Server-only fetch wrapper that talks to the Laravel backend.
 * The `server-only` import at the top hard-fails the build if any
 * client component tries to pull this in by accident — back-stops
 * the eslint banned-imports rule.
 *
 * Always attaches:
 *   X-Proxy-Secret      → ProxyOriginMiddleware
 *   X-Correlation-Id    → CorrelationIdMiddleware (passed through if
 *                         caller provided one, otherwise generated)
 *   Accept              → application/json
 */
export interface BackendFetchOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
  correlationId?: string;
  /** Forward an end-user JWT — usually pulled from the access cookie by
   *  the BFF route handler (TASK 0.15). */
  bearer?: string;
  /** ADR-017 — guest cart cookie mediation. Browser ↔ BFF ↔ Laravel
   *  cross-origin olduğundan cookie jar paylaşılmıyor; BFF her iki
   *  yönde de manuel forward etmeli. Bu alana iletilen header
   *  (`name=value; name2=value2`) Laravel'e Cookie header olarak
   *  geçer. */
  cookie?: string;
}

export async function backendFetch(
  path: string,
  options: BackendFetchOptions = {},
): Promise<Response> {
  const { body, correlationId, bearer, cookie, headers, ...rest } = options;

  const requestHeaders = new Headers(headers);
  requestHeaders.set('X-Proxy-Secret', serverEnv.backendProxySecret());
  requestHeaders.set('Accept', 'application/json');
  requestHeaders.set('Origin', new URL(serverEnv.laravelApiUrl()).origin);

  if (body !== undefined && !requestHeaders.has('Content-Type')) {
    requestHeaders.set('Content-Type', 'application/json');
  }
  if (correlationId !== undefined) {
    requestHeaders.set('X-Correlation-Id', correlationId);
  }
  if (bearer !== undefined) {
    requestHeaders.set('Authorization', `Bearer ${bearer}`);
  }
  if (cookie !== undefined && cookie !== '') {
    requestHeaders.set('Cookie', cookie);
  }

  const url = `${serverEnv.laravelApiUrl().replace(/\/$/, '')}${path.startsWith('/') ? path : `/${path}`}`;

  return fetch(url, {
    ...rest,
    headers: requestHeaders,
    body: body === undefined ? undefined : JSON.stringify(body),
    cache: 'no-store',
  });
}

/**
 * Convenience wrapper that decodes JSON + throws on non-2xx.
 */
export async function backendJson<T = unknown>(
  path: string,
  options: BackendFetchOptions = {},
): Promise<T> {
  const response = await backendFetch(path, options);
  const text = await response.text();
  let data: unknown;
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    throw new BackendError(response.status, 'Invalid JSON from backend', text);
  }

  if (!response.ok) {
    throw new BackendError(response.status, 'Backend request failed', data);
  }

  return data as T;
}

export class BackendError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly body: unknown,
  ) {
    super(message);
    this.name = 'BackendError';
  }
}
