/**
 * Server-only env access. NEVER imported from a 'use client' component.
 *
 * Backend secrets (LARAVEL_API_URL, BACKEND_PROXY_SECRET) live in env
 * vars without the NEXT_PUBLIC_ prefix so Next.js's bundler does NOT
 * inline them into the client JavaScript bundle. The dedicated
 * scripts/check-backend-leak.sh CI gate enforces this at build time
 * (TASK 0.15).
 *
 * SECURITY.md §3.2 — Direct Backend Access Prevention.
 */

function required(name: string): string {
  const value = process.env[name];
  if (value === undefined || value === '') {
    throw new Error(
      `[env] Required server-side env var is missing: ${name}. ` +
        `Set it in .env.local (never commit) or your deployment env.`,
    );
  }
  return value;
}

function optional(name: string, fallback: string): string {
  return process.env[name] ?? fallback;
}

/**
 * Server-side env. Only call inside Server Components, route handlers,
 * or Server Actions — never in 'use client' files.
 */
export const serverEnv = {
  /** Internal Laravel API URL (Docker DNS in prod, localhost in dev). */
  laravelApiUrl: () => required('LARAVEL_API_URL'),
  /** Shared secret consumed by ProxyOriginMiddleware on the backend. */
  backendProxySecret: () => required('BACKEND_PROXY_SECRET'),
  /** Logging verbosity. */
  logLevel: () => optional('LOG_LEVEL', 'info'),
} as const;

/**
 * Client-safe env (NEXT_PUBLIC_*). Backend URLs and secrets are
 * forbidden here — the eslint banned-imports rule enforces it.
 */
export const publicEnv = {
  appName: process.env.NEXT_PUBLIC_APP_NAME ?? 'E-Ticaret Demo',
  defaultCurrency: process.env.NEXT_PUBLIC_DEFAULT_CURRENCY ?? 'TRY',
} as const;
