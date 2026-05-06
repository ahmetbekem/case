import { NextRequest, NextResponse } from 'next/server';

import { backendFetch, BackendError } from '@/shared/api/server-fetch';

import { readAccessToken } from '../_lib/cookies';
import { correlationIdFrom } from '../_lib/errors';
import { tryRefresh } from '../_lib/refresh';

/**
 * Catch-all proxy — every /api/* path that doesn't have a dedicated
 * route handler ends up here. Responsibilities:
 *
 *   1. Translate the request method + body + safe headers into a
 *      backendFetch (which automatically attaches X-Proxy-Secret +
 *      Origin + correlation id).
 *   2. Pull the access token out of the httpOnly cookie and inject it
 *      as Authorization: Bearer.
 *   3. On a 401 — try refresh once, then replay the request with the
 *      new access token. Any further failure is propagated as-is so
 *      the client sees Laravel's own envelope (e.g. 401
 *      INVALID_ACCESS_TOKEN, 404 ORDER_NOT_FOUND, 429 TOO_MANY_REQUESTS).
 *   4. Stream Laravel's response body back unchanged + mirror the
 *      X-Correlation-Id so the browser can stitch logs.
 *
 * Auth-specific handlers (login/register/refresh/logout/me) sit ahead
 * of this in the route table — Next.js prefers more-specific matches.
 */

const FORWARDED_REQUEST_HEADERS = ['accept', 'content-type', 'x-correlation-id'];

const METHODS_WITH_BODY = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

async function readBody(request: NextRequest, method: string): Promise<string | undefined> {
  if (!METHODS_WITH_BODY.has(method)) {
    return undefined;
  }
  const raw = await request.text();
  return raw === '' ? undefined : raw;
}

function buildForwardHeaders(request: NextRequest): Record<string, string> {
  const headers: Record<string, string> = {};
  for (const name of FORWARDED_REQUEST_HEADERS) {
    const value = request.headers.get(name);
    if (value !== null && value !== '') {
      headers[name] = value;
    }
  }
  return headers;
}

/**
 * Internal — does the actual backendFetch call. Body must be a raw JSON
 * string (we re-stringify in backendFetch otherwise, which would
 * double-encode). Falls back to passing rawBody verbatim.
 */
async function callBackend(
  path: string,
  method: string,
  rawBody: string | undefined,
  headers: Record<string, string>,
  bearer: string | undefined,
  correlationId: string | undefined,
): Promise<Response> {
  // backendFetch JSON.stringify's body, but we already have a raw JSON
  // string from the browser — parse it back so the helper produces an
  // identical wire format.
  const parsedBody = rawBody !== undefined ? safeJsonParse(rawBody) : undefined;

  return backendFetch(path, {
    method,
    headers,
    body: parsedBody,
    bearer,
    correlationId,
  });
}

function safeJsonParse(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    // Not JSON (e.g. multipart upload — out of scope for now). Pass through
    // as a string; backendFetch will JSON-encode it which is wrong for
    // binary, but our API is JSON-only at this point.
    return text;
  }
}

async function proxy(method: string, request: NextRequest, pathSegments: string[]): Promise<NextResponse> {
  const path = '/' + pathSegments.join('/');
  const fullPath = `${path}${request.nextUrl.search}`;
  const headers = buildForwardHeaders(request);
  const correlationId = correlationIdFrom(request.headers);

  const rawBody = await readBody(request, method);
  let access = await readAccessToken();

  let response: Response;
  try {
    response = await callBackend(fullPath, method, rawBody, headers, access, correlationId);
  } catch (error) {
    return errorToProxyResponse(error);
  }

  // 401 retry-with-refresh — only attempt if we had an access token
  // (no point trying refresh for a public endpoint that returned 401)
  if (response.status === 401 && access !== undefined) {
    const refreshed = await tryRefresh();
    if (refreshed.ok) {
      access = refreshed.accessToken;
      try {
        response = await callBackend(fullPath, method, rawBody, headers, access, correlationId);
      } catch (error) {
        return errorToProxyResponse(error);
      }
    }
  }

  return forwardResponse(response);
}

async function forwardResponse(response: Response): Promise<NextResponse> {
  const body = await response.text();
  const responseHeaders = new Headers();
  const contentType = response.headers.get('content-type');
  if (contentType !== null) responseHeaders.set('content-type', contentType);
  const correlationId = response.headers.get('x-correlation-id');
  if (correlationId !== null) responseHeaders.set('x-correlation-id', correlationId);
  // BFF cevapları her zaman fresh olmalı — özellikle cart/orders gibi
  // user-specific dynamic data. Browser cache'i devreden çıkar; mutation
  // sonrası veya hard refresh sonrası eski payload kalmasın.
  responseHeaders.set('cache-control', 'no-store, max-age=0');

  return new NextResponse(body === '' ? null : body, {
    status: response.status,
    statusText: response.statusText,
    headers: responseHeaders,
  });
}

function errorToProxyResponse(error: unknown): NextResponse {
  if (error instanceof BackendError) {
    const payload =
      error.body && typeof error.body === 'object'
        ? error.body
        : { error: { code: 'BACKEND_ERROR', message: error.message } };
    return NextResponse.json(payload, { status: error.status });
  }
  return NextResponse.json(
    { error: { code: 'PROXY_NETWORK_ERROR', message: 'Could not reach backend' } },
    { status: 502 },
  );
}

interface RouteContext {
  params: Promise<{ path: string[] }>;
}

export async function GET(request: NextRequest, ctx: RouteContext) {
  const { path } = await ctx.params;
  return proxy('GET', request, path);
}

export async function POST(request: NextRequest, ctx: RouteContext) {
  const { path } = await ctx.params;
  return proxy('POST', request, path);
}

export async function PUT(request: NextRequest, ctx: RouteContext) {
  const { path } = await ctx.params;
  return proxy('PUT', request, path);
}

export async function PATCH(request: NextRequest, ctx: RouteContext) {
  const { path } = await ctx.params;
  return proxy('PATCH', request, path);
}

export async function DELETE(request: NextRequest, ctx: RouteContext) {
  const { path } = await ctx.params;
  return proxy('DELETE', request, path);
}
