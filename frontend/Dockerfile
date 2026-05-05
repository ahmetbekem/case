# syntax=docker/dockerfile:1.7

# ─────────────────────────────────────────────────────────────────────
# Stage 1 — Dependencies (cached on lockfile changes only)
# ─────────────────────────────────────────────────────────────────────
FROM node:24-alpine AS deps

# pnpm via Corepack (no global install pollution)
RUN corepack enable && corepack prepare pnpm@9.15.9 --activate

WORKDIR /app
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile --prod=false


# ─────────────────────────────────────────────────────────────────────
# Stage 2 — Build (next build → .next/standalone)
# ─────────────────────────────────────────────────────────────────────
FROM node:24-alpine AS builder

RUN corepack enable && corepack prepare pnpm@9.15.9 --activate

WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .

# Build-time placeholders so `next build` doesn't crash on missing
# server env. Real values are injected at runtime by docker-compose.
ENV LARAVEL_API_URL=http://placeholder-overridden-at-runtime
ENV BACKEND_PROXY_SECRET=placeholder

RUN pnpm build


# ─────────────────────────────────────────────────────────────────────
# Stage 3 — Runtime (only the standalone server)
# next.config.ts has output=standalone so the build emits a self-
# contained server bundle in .next/standalone — copy it + the static
# assets and we're done. Final image ~150MB instead of ~600MB.
# ─────────────────────────────────────────────────────────────────────
FROM node:24-alpine AS runtime

RUN apk add --no-cache curl

# Run as non-root
RUN addgroup -g 1001 -S nextjs && adduser -S nextjs -u 1001

WORKDIR /app

COPY --from=builder --chown=nextjs:nextjs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nextjs /app/.next/static ./.next/static
COPY --from=builder --chown=nextjs:nextjs /app/public ./public

USER nextjs

ENV NODE_ENV=production
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -fsS http://127.0.0.1:3000/ -o /dev/null || exit 1

CMD ["node", "server.js"]
