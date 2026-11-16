# 🔒 E-Ticaret Demo Projesi - Security Architecture

> **CRITICAL:** Bu doküman 5 ana güvenlik tehdidini ve mitigation'larını tanımlar. Implementation **kesinlikle** bu kurallara uymak zorundadır. Güvenlik konusunda kısayol yoktur.

**Version:** 1.0
**Standards Reference:** OWASP Top 10 (2021), OWASP API Security Top 10 (2023)
**Compliance:** KVKK uyumlu (kişisel veri sızıntısı yok)

---

## 📋 TABLE OF CONTENTS

1. [Threat Model Overview](#1-threat-model-overview)
2. [API Endpoint Exposure Protection](#2-api-endpoint-exposure-protection)
3. [Direct Backend Access Prevention](#3-direct-backend-access-prevention)
4. [Token Security & Storage](#4-token-security--storage)
5. [Authorization & Access Control](#5-authorization--access-control)
6. [CORS Policy](#6-cors-policy)
7. [Rate Limiting](#7-rate-limiting)
8. [Input Validation & Sanitization](#8-input-validation--sanitization)
9. [Secrets Management](#9-secrets-management)
10. [Security Testing Checklist](#10-security-testing-checklist)
11. [Incident Response](#11-incident-response)
12. [Guest Cart Security (ADR-017)](#12-guest-cart-security-adr-017)

---

## 1. THREAT MODEL OVERVIEW

### 1.1 Trust Boundaries

```
┌──────────────┐  PUBLIC INTERNET  ┌─────────────────┐  PRIVATE NETWORK  ┌───────────┐
│   Browser    │ ────────────────► │  Next.js (BFF)  │ ────────────────► │  Laravel  │
│  (untrusted) │   HTTPS only      │   (semi-trusted) │   Proxy secret    │ (trusted) │
└──────────────┘                   └─────────────────┘                   └───────────┘
                                                                                │
                                                                                ▼
                                                                  ┌──────────────────┐
                                                                  │ PostgreSQL/Redis │
                                                                  │  (private only)  │
                                                                  └──────────────────┘
```

**Trust Levels:**
- **Browser:** ASLA güvenilmez. Her input zararlı varsayılır.
- **Next.js Proxy:** Yarı güvenilir. Server-side runtime ama public-facing.
- **Laravel API:** Güvenilir. Sadece proxy ile konuşur, internete açık DEĞİL.
- **PostgreSQL/Redis/RabbitMQ:** Güvenilir. Sadece backend network'ünden erişilebilir.

### 1.2 Threat Categories (Project Requirements)

| # | Threat | Severity | Mitigation Section |
|---|--------|----------|-------------------|
| 1 | API Endpoint Exposure | HIGH | §2 |
| 2 | Direct Backend Access | HIGH | §3 |
| 3 | Token Theft / Storage | CRITICAL | §4 |
| 4 | Unauthorized Access | CRITICAL | §5 |
| 5 | CORS Misconfiguration | HIGH | §6 |

### 1.3 Defense-in-Depth Principle

Her katmanda güvenlik kontrolleri vardır. Bir katman bypass edilse bile diğerleri korur:

```
Layer 1: Network    (Firewall, IP whitelist)
Layer 2: Transport  (HTTPS, HSTS)
Layer 3: Proxy      (Origin validation, rate limit)
Layer 4: Auth       (JWT verification)
Layer 5: Authz      (Role + Resource ownership)
Layer 6: Validation (FormRequest, sanitization)
Layer 7: Domain     (Business invariants)
Layer 8: Audit      (Logging, anomaly detection)
```

---

## 2. API ENDPOINT EXPOSURE PROTECTION

### 2.1 The Problem

Eğer Laravel API'si direkt internete açıksa:
- Saldırgan endpoint'leri keşfedebilir (`/api/v1/...`)
- Brute force, scraping, DDoS hedefi olur
- Proxy katmanını bypass ederek doğrudan istek atabilir

### 2.2 Mitigation Strategy

**İki katmanlı koruma (defense-in-depth):**

#### Layer 1: Network Isolation

Production'da Laravel **HİÇBİR ŞEKİLDE** public IP'de bulunmaz:

```yaml
# docker-compose.production.yml
services:
  backend:
    # NO ports: published! Only internal network
    networks:
      - internal
    expose:
      - "9000"  # Only visible within Docker network

  frontend:
    ports:
      - "443:3000"  # Only frontend is public
    networks:
      - internal

networks:
  internal:
    driver: bridge
    internal: false  # Frontend needs egress to other services
```

**Kubernetes / cloud equivalent:**
- Backend service: `ClusterIP` (internal only) — never `LoadBalancer` or `NodePort`
- Security group / firewall: Backend port sadece frontend pod/instance'tan açık

#### Layer 2: Proxy Origin Verification

Network bypass edilse bile Laravel kendisini korur:

**Next.js → Laravel her request'inde shared secret header:**

```typescript
// frontend/src/shared/api/server-fetch.ts (Next.js side)
export async function backendFetch(path: string, options: RequestInit = {}) {
  const headers = new Headers(options.headers);
  headers.set('X-Proxy-Secret', process.env.BACKEND_PROXY_SECRET!);
  headers.set('X-Correlation-Id', getCorrelationId());

  return fetch(`${process.env.LARAVEL_API_URL}${path}`, {
    ...options,
    headers,
  });
}
```

**Laravel middleware doğrular:**

```php
<?php

declare(strict_types=1);

namespace Presentation\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ProxyOriginMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('security.proxy_secret');
        $providedSecret = $request->header('X-Proxy-Secret');

        if (empty($expectedSecret)) {
            throw new \RuntimeException('BACKEND_PROXY_SECRET not configured');
        }

        if (empty($providedSecret) || !hash_equals($expectedSecret, $providedSecret)) {
            // Audit log: bypass attempt
            app(AuditLoggerInterface::class)->log(new AuditEvent(
                actor: 'anonymous',
                actorIp: $request->ip() ?? 'unknown',
                action: 'security.proxy_bypass_attempt',
                resource: $request->getPathInfo(),
                module: 'security',
                result: 'failure',
                correlationId: $request->header('X-Correlation-Id') ?? 'no-correlation',
                userAgent: $request->userAgent(),
                metadata: ['method' => $request->getMethod()],
            ));

            throw new AccessDeniedHttpException('Direct backend access not allowed');
        }

        return $next($request);
    }
}
```

**Registration (routes/api.php):**

```php
Route::prefix('api/v1')
    ->middleware(['proxy.origin', 'correlation.id'])  // ← FIRST middleware
    ->group(function () {
        require __DIR__ . '/api/identity.php';
        // ...
    });
```

### 2.3 Secret Rotation

`BACKEND_PROXY_SECRET` minimum 64 karakter, cryptographically random:

```bash
# Generate secret
php -r 'echo bin2hex(random_bytes(32));'
# or
openssl rand -hex 32
```

**Rotation cadence:** 90 günde bir, veya secret leak şüphesinde anında.

**Zero-downtime rotation:**
1. Laravel'de eski + yeni secret'ı kabul et (geçici dual-secret)
2. Next.js deploy (yeni secret)
3. Laravel'den eski secret'ı kaldır

### 2.4 Health Check Endpoint Exception

Health check endpoint'leri proxy origin kontrolünden muaf olabilir, ama:

```php
// Health check explicit ve minimal
Route::get('/health', fn() => response()->json(['status' => 'ok']))
    ->withoutMiddleware('proxy.origin');
```

Bu endpoint **HİÇBİR sensitive bilgi** dönmemeli (versiyon, ortam, DB durumu vs.).

---

## 3. DIRECT BACKEND ACCESS PREVENTION

### 3.1 The Problem

Frontend kodu derlendiğinde browser'a gönderilir. Eğer kodda:
```typescript
// ❌ FELAKETİ HAZIRLAYAN KOD
const apiUrl = "https://api.example.com";
fetch(`${apiUrl}/users`, { ... });
```

Browser'da Network tab açılır, Laravel URL'i gözükür, saldırgan doğrudan istek atar.

### 3.2 Mitigation Strategy

#### Rule 1: Backend URL **ASLA** browser'a sızmaz

**Environment variable ayrımı:**

```bash
# frontend/.env.local
# ❌ BU PUBLIC OLUR — Laravel URL'i ASLA buraya yazma
NEXT_PUBLIC_APP_NAME="E-Ticaret Demo"
NEXT_PUBLIC_DEFAULT_CURRENCY="TRY"

# ✅ BU SERVER-SIDE — Browser'a sızmaz
LARAVEL_API_URL=http://backend:9000/api/v1   # Internal Docker DNS
BACKEND_PROXY_SECRET=<64-char-random-hex>
JWT_COOKIE_DOMAIN=app.example.com
```

**Rule:** `NEXT_PUBLIC_*` prefix browser'a sızar. Backend ile ilgili HİÇBİR ŞEY bu prefix ile başlamamalı.

#### Rule 2: Tüm Backend İstekleri Server-Side

**Yasak pattern:**

```typescript
// ❌ Client component'ten direkt backend çağrısı
'use client';
export function ProductList() {
  useEffect(() => {
    fetch(process.env.NEXT_PUBLIC_API_URL + '/products');  // BACKEND URL LEAKED
  }, []);
}
```

**Doğru pattern:**

```typescript
// ✅ Browser → Next.js /api proxy → Laravel
'use client';
export function ProductList() {
  useEffect(() => {
    fetch('/api/catalog/products');  // Same origin, proxy decides where to go
  }, []);
}
```

```typescript
// ✅ frontend/src/app/api/[...path]/route.ts (catch-all proxy)
import { NextRequest, NextResponse } from 'next/server';
import { backendFetch } from '@/shared/api/server-fetch';

export async function GET(req: NextRequest, { params }: { params: { path: string[] } }) {
  const path = '/' + params.path.join('/');
  const search = req.nextUrl.search;

  const response = await backendFetch(`${path}${search}`, {
    method: 'GET',
    headers: forwardHeaders(req),  // user JWT cookie, correlation-id
  });

  return new NextResponse(response.body, {
    status: response.status,
    headers: response.headers,
  });
}

// Repeat for POST, PUT, PATCH, DELETE
```

#### Rule 3: Build-Time Leak Detection

CI'da test eder:

```bash
# scripts/check-backend-leak.sh
#!/usr/bin/env bash
set -e

cd frontend
pnpm build

# .next klasöründe Laravel URL aramaya çalış
if grep -r "LARAVEL_API_URL\|api.example.com\|backend:9000" .next/static/ 2>/dev/null; then
  echo "❌ FAIL: Backend URL leaked into client bundle!"
  exit 1
fi

# Proxy secret de sızmamalı
if grep -r "BACKEND_PROXY_SECRET" .next/static/ 2>/dev/null; then
  echo "❌ FAIL: Proxy secret leaked!"
  exit 1
fi

echo "✅ No backend secrets in client bundle"
```

**CI integration:**

```yaml
# .github/workflows/frontend-ci.yml
- name: Verify no backend leaks
  run: bash scripts/check-backend-leak.sh
```

#### Rule 4: Laravel URL Constant Banned

ESLint rule (frontend):

```javascript
// frontend/.eslintrc.js
module.exports = {
  rules: {
    'no-restricted-globals': ['error',
      {
        name: 'process.env.LARAVEL_API_URL',
        message: 'Use server-only fetcher from @/shared/api/server-fetch',
      },
    ],
    'no-restricted-imports': ['error', {
      paths: [{
        name: 'next/server',
        importNames: ['LARAVEL_API_URL'],
        message: 'Backend URL is server-only',
      }],
    }],
  },
};
```

### 3.3 Production Deployment Verification

Deployment sonrası manuel doğrulama:

```bash
# Production frontend domain'inden curl
curl -v https://app.example.com/

# Browser DevTools'ta Network tab:
# - Tüm /api/* istekleri SAME ORIGIN olmalı
# - HİÇBİR istek backend domain'ine gitmemeli

# Laravel domain'i (varsa) — public DNS resolution olmamalı
nslookup api.example.com  # NXDOMAIN beklenir
# veya
curl https://api.example.com/  # Connection refused / firewall block
```

---

## 4. TOKEN SECURITY & STORAGE

### 4.1 Token Strategy Summary

| Token | Lifetime | Storage | Transmission |
|-------|----------|---------|--------------|
| Access Token (JWT) | 15 minutes | httpOnly cookie | Cookie header |
| Refresh Token | 7 days | httpOnly cookie + DB hash | Cookie header |

### 4.2 Cookie Configuration

**ZORUNLU flag'ler:**

```typescript
// Next.js setting cookies
import { cookies } from 'next/headers';

cookies().set('access_token', accessToken, {
  httpOnly: true,            // ✅ JS erişemez (XSS koruması)
  secure: true,              // ✅ Sadece HTTPS
  sameSite: 'strict',        // ✅ CSRF koruması (cross-site request'lerde gönderilmez)
  path: '/',
  maxAge: 15 * 60,           // 15 minutes
  domain: process.env.JWT_COOKIE_DOMAIN,
});

cookies().set('refresh_token', refreshToken, {
  httpOnly: true,
  secure: true,
  sameSite: 'strict',
  path: '/api/auth',         // ⭐ Sadece auth endpoint'lerine gönderilir
  maxAge: 7 * 24 * 60 * 60,  // 7 days
  domain: process.env.JWT_COOKIE_DOMAIN,
});
```

**Refresh token'ın `path: '/api/auth'` olması kritik:**
- Normal API isteklerinde refresh token gönderilmez (gereksiz exposure azalır)
- Sadece `/api/auth/refresh` endpoint'inde browser otomatik gönderir

### 4.3 JWT Generation Standards

**Algorithm:** RS256 (asymmetric) — production için
**Development fallback:** HS256 (symmetric, simpler) — sadece local dev

**Required claims:**

```json
{
  "iss": "eticaret-demo-api",
  "aud": "eticaret-demo-app",
  "sub": "550e8400-e29b-41d4-a716-446655440000",  // user id
  "iat": 1714823400,
  "exp": 1714824300,
  "jti": "unique-token-id",                        // for revocation
  "role": "customer",
  "fingerprint": "sha256-of-user-agent+ip"        // optional binding
}
```

**Forbidden:**
- ❌ `none` algorithm
- ❌ Plaintext password içermek
- ❌ Sensitive data (kredi kartı, TCKN, vb.)
- ❌ Algorithm confusion: server `RS256` bekliyorken `HS256` kabul etmek

**Laravel JWT config:**

```php
// config/jwt.php
return [
    'algo' => env('JWT_ALGO', 'RS256'),
    'public_key' => env('JWT_PUBLIC_KEY'),       // .pem file path
    'private_key' => env('JWT_PRIVATE_KEY'),
    'ttl' => 15,                                  // minutes
    'refresh_ttl' => 60 * 24 * 7,                // 7 days
    'required_claims' => ['iss', 'iat', 'exp', 'sub', 'jti'],
    'leeway' => 5,                                // clock skew (seconds)
];
```

### 4.4 Refresh Token Rotation & Replay Detection

#### State Machine

```
┌─────────┐
│ Issued  │
└────┬────┘
     │
     ├──► Used ──► [revoked_at SET, replaced_by_token_id SET, new token issued]
     │              │
     │              └──► Used AGAIN? ──► REPLAY DETECTED
     │                                   ├─► Revoke ENTIRE family
     │                                   ├─► Audit log critical event
     │                                   └─► Logout user from ALL devices
     │
     ├──► Logout ──► [revoked_at SET, reason='logout']
     │
     └──► Expires (7 days) ──► Cleanup job removes
```

#### Implementation

```php
// Application/Identity/Handlers/Commands/RefreshTokenHandler.php
protected function doHandle(CommandInterface $command): TokenPairDTO
{
    assert($command instanceof RefreshTokenCommand);

    $providedToken = $command->refreshToken;
    $tokenHash = hash('sha256', $providedToken);

    return $this->transaction->run(function () use ($tokenHash, $command) {
        $token = $this->refreshTokenRepository->findByHash($tokenHash);

        if ($token === null) {
            throw InvalidRefreshTokenException::notFound();
        }

        // ⚠️ REPLAY DETECTION
        if ($token->isRevoked()) {
            // This token was already used — possible theft
            $this->refreshTokenRepository->revokeFamilyExcept(
                familyId: $token->getFamilyId(),
                exceptId: null,  // revoke ALL
                reason: 'replay_detected',
            );

            $this->auditLogger->log(new AuditEvent(
                actor: $token->getUserId()->value(),
                actorIp: $command->getUserIp(),
                action: 'security.refresh_token_replay',
                resource: 'user:' . $token->getUserId()->value(),
                module: 'identity',
                result: 'failure',
                correlationId: $command->getCorrelationId(),
                metadata: ['familyId' => $token->getFamilyId()->value()],
            ));

            throw RefreshTokenReplayException::detected();
        }

        if ($token->isExpired()) {
            throw InvalidRefreshTokenException::expired();
        }

        // ✅ Valid — rotate
        $newAccessToken = $this->jwtIssuer->issueAccessToken($token->getUserId());
        $newRefreshToken = RefreshToken::generate(
            userId: $token->getUserId(),
            familyId: $token->getFamilyId(),  // SAME family — rotation chain
            userAgent: $command->getUserAgent(),
            ipAddress: $command->getUserIp(),
        );

        // Mark old as rotated
        $token->markAsRotated($newRefreshToken->getId());
        $this->refreshTokenRepository->save($token);

        $this->refreshTokenRepository->save($newRefreshToken);

        return new TokenPairDTO(
            accessToken: $newAccessToken->jwt(),
            refreshToken: $newRefreshToken->plaintextValue(),  // hash saved in DB
            expiresIn: 15 * 60,
        );
    });
}
```

#### Concurrent Refresh Grace Period

Mobile app veya çift tab açma durumunda iki concurrent refresh isteği yarış edebilir. **5 saniyelik grace window:**

```php
public function isRevoked(): bool
{
    if ($this->revokedAt === null) return false;

    // Grace period: aynı saniye içinde rotate edilmiş ise tekrar kabul edilebilir
    $secondsSinceRevoke = time() - $this->revokedAt->getTimestamp();
    return $secondsSinceRevoke > 5;
}
```

> Bu sadece `revoke_reason = 'rotated'` için geçerli. `replay_detected`, `logout`, `admin_revoke` için grace YOK.

### 4.5 CSRF Protection

**SameSite=Strict cookies CSRF saldırılarının %95'ini engeller**, ancak defense-in-depth için ek katman:

#### Strategy: Origin Header Validation

State-changing endpoint'lerde (POST, PUT, PATCH, DELETE) Origin header zorunlu:

```php
<?php

declare(strict_types=1);

namespace Presentation\Shared\Middleware;

final class CsrfOriginMiddleware
{
    private const ALLOWED_ORIGINS = [
        // From config — production'da sadece bir tane
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $origin = $request->header('Origin') ?? $request->header('Referer');

        if ($origin === null) {
            throw new AccessDeniedHttpException('Origin header required for state-changing requests');
        }

        $allowedOrigins = config('security.allowed_origins');
        $matched = false;

        foreach ($allowedOrigins as $allowed) {
            if (str_starts_with($origin, $allowed)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            app(AuditLoggerInterface::class)->log(new AuditEvent(
                actor: $request->attributes->get('user_id', 'anonymous'),
                actorIp: $request->ip() ?? 'unknown',
                action: 'security.csrf_origin_mismatch',
                resource: $request->getPathInfo(),
                module: 'security',
                result: 'failure',
                correlationId: $request->header('X-Correlation-Id') ?? 'no-correlation',
                metadata: ['origin' => $origin, 'method' => $request->getMethod()],
            ));

            throw new AccessDeniedHttpException('Invalid origin');
        }

        return $next($request);
    }
}
```

> **Not:** Bu middleware Next.js proxy'den GELDIĞINDE Origin header'ı Next.js domain olur. Proxy bunu **değiştirmemeli** — backend bunun gerçek origin olduğunu varsayar.

#### Strategy 2: Double-Submit Cookie (Optional Enhancement)

Yüksek güvenlik gerektiren endpoint'ler için (örn: password change):

```typescript
// Frontend: cookie ve header'a aynı CSRF token
const csrfToken = generateRandomToken();
cookies().set('csrf_token', csrfToken, { httpOnly: false, sameSite: 'strict' });

// Request'te:
fetch('/api/auth/change-password', {
  headers: { 'X-CSRF-Token': csrfToken },
});
```

```php
// Backend: cookie ve header'ın eşleştiğini doğrula
if (!hash_equals($request->cookie('csrf_token'), $request->header('X-CSRF-Token'))) {
    throw new AccessDeniedHttpException('CSRF token mismatch');
}
```

### 4.6 Token Revocation

Logout veya admin revoke durumunda:

```php
// Logout
public function logout(LogoutUserCommand $command): void
{
    $this->refreshTokenRepository->revokeFamilyExcept(
        familyId: $command->familyId,
        exceptId: null,
        reason: 'logout',
    );

    // Access token'ı da blacklist'e ekle (Redis, TTL = remaining lifetime)
    $this->jwtBlacklist->add(
        jti: $command->accessTokenJti,
        ttl: $command->accessTokenRemainingSeconds,
    );
}
```

**JWT Blacklist (Redis):**
- Key: `jwt:blacklist:{jti}`
- TTL: token'ın kalan ömrü (max 15 dakika)
- Her request'te middleware kontrol eder

```php
final class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);
        $payload = $this->jwtVerifier->verify($token);

        if ($this->blacklist->contains($payload->jti)) {
            throw new UnauthorizedHttpException('Token revoked');
        }

        $request->attributes->set('user_id', $payload->sub);
        return $next($request);
    }
}
```

---

## 5. AUTHORIZATION & ACCESS CONTROL

### 5.1 Three Layers of Authorization

```
┌─────────────────────────────────────────────────┐
│ Layer 1: Authentication (jwt.auth)              │
│  → Sen kimsin? JWT geçerli mi?                  │
└────────────────────┬────────────────────────────┘
                     ▼
┌─────────────────────────────────────────────────┐
│ Layer 2: Role-Based (role:admin)                │
│  → Bu rol bu endpoint'e erişebilir mi?          │
└────────────────────┬────────────────────────────┘
                     ▼
┌─────────────────────────────────────────────────┐
│ Layer 3: Resource Ownership                     │
│  → Bu kullanıcı BU resource'a sahip mi?         │
└─────────────────────────────────────────────────┘
```

### 5.2 Role-Based Access Control (RBAC)

**Roles:**
- `customer` — normal kullanıcı, sipariş verebilir
- `admin` — ürün yönetimi, tüm siparişleri görme

**Middleware:**

```php
<?php

declare(strict_types=1);

namespace Presentation\Shared\Middleware;

final class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $userRole = $request->attributes->get('user_role');

        if (!in_array($userRole, $allowedRoles, true)) {
            app(AuditLoggerInterface::class)->log(new AuditEvent(
                actor: $request->attributes->get('user_id', 'unknown'),
                actorIp: $request->ip() ?? 'unknown',
                action: 'security.role_denied',
                resource: $request->getPathInfo(),
                module: 'security',
                result: 'failure',
                correlationId: $request->header('X-Correlation-Id') ?? 'no-correlation',
                metadata: ['userRole' => $userRole, 'requiredRoles' => $allowedRoles],
            ));

            throw new AccessDeniedHttpException('Insufficient role');
        }

        return $next($request);
    }
}
```

**Usage:**

```php
Route::middleware(['jwt.auth', 'role:admin'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
});
```

### 5.3 Resource Ownership (IDOR Prevention)

**The Threat:** User A tries to access User B's order:

```
GET /api/v1/sales/orders/{B's order ID}
Authorization: Bearer {A's token}
```

**Without ownership check:** Order returned. **IDOR vulnerability.**

#### Pattern 1: Domain-Level Invariant (Preferred)

```php
// Domain/Sales/Entities/Order.php
public function assertCanBeViewedBy(UserId $viewerId, ?Role $viewerRole = null): void
{
    // Admin can view any order
    if ($viewerRole === Role::ADMIN) {
        return;
    }

    // Owner can view own order
    if (!$this->userId->equals($viewerId)) {
        throw OrderAccessDeniedException::notOwner();
    }
}
```

```php
// Application/Sales/Handlers/Queries/GetOrderHandler.php
protected function doHandle(QueryInterface $query): OrderDTO
{
    assert($query instanceof GetOrderQuery);

    $order = $this->orderRepository->findById(OrderId::fromString($query->orderId));

    if ($order === null) {
        throw OrderNotFoundException::withId($query->orderId);
    }

    // ⭐ Resource ownership check
    $order->assertCanBeViewedBy(
        UserId::fromString($query->userId),
        Role::from($query->userRole),
    );

    return OrderDTO::fromEntity($order);
}
```

> **Önemli:** `OrderAccessDeniedException` ve `OrderNotFoundException` HTTP 404 dönmeli (403 değil). 403 döndürmek "bu order var ama erişemezsin" bilgisini sızdırır (information disclosure).

#### Pattern 2: Repository-Level Filter (Alternative)

```php
public function findByIdForUser(OrderId $orderId, UserId $userId): ?Order
{
    $model = $this->query()
        ->where('id', $orderId->value())
        ->where('user_id', $userId->value())  // ⭐ Filter at query
        ->first();

    return $model ? $this->mapper->toEntity($model) : null;
}
```

Bu pattern admin'in tüm siparişleri görme ihtiyacını karşılamaz, o yüzden **Pattern 1 tercih edilir**.

### 5.4 Authorization Matrix

| Endpoint | Anonymous | Customer | Admin |
|----------|-----------|----------|-------|
| `GET /catalog/products` | ✅ | ✅ | ✅ |
| `GET /catalog/products/{id}` | ✅ | ✅ | ✅ |
| `POST /catalog/products` | ❌ | ❌ | ✅ |
| `PUT /catalog/products/{id}` | ❌ | ❌ | ✅ |
| `DELETE /catalog/products/{id}` | ❌ | ❌ | ✅ |
| `GET /sales/cart` | ❌ | ✅ (own) | ✅ (own) |
| `POST /sales/cart/items` | ❌ | ✅ | ✅ |
| `POST /sales/orders` | ❌ | ✅ | ✅ |
| `GET /sales/orders/{id}` | ❌ | ✅ (own) | ✅ (any) |
| `GET /sales/orders` | ❌ | ✅ (own only) | ✅ (all) |
| `POST /sales/orders/{id}/cancel` | ❌ | ✅ (own, pending) | ✅ (any) |
| `GET /currency/rates` | ✅ | ✅ | ✅ |
| `POST /auth/register` | ✅ | ❌ | ❌ |
| `POST /auth/login` | ✅ | ❌ | ❌ |
| `POST /auth/refresh` | ✅ (cookie) | ✅ | ✅ |
| `POST /auth/logout` | ❌ | ✅ | ✅ |

---

## 6. CORS POLICY

### 6.1 Threat Model

CORS yanlış yapılandırılırsa:
- `Access-Control-Allow-Origin: *` + credentials → **kritik vulnerability**
- Dynamic reflection of Origin header without validation → **bypass mümkün**
- Wildcard subdomain (`*.example.com`) → subdomain takeover saldırısına açık

### 6.2 Our Architecture Advantage

Browser → Next.js → Laravel akışında **CORS aslında devreye girmez**:
- Browser hep kendi domain'ine istek atar (`/api/...`)
- Next.js → Laravel server-to-server, CORS yok
- Laravel'in CORS header'ları döndürmesine **gerek yok**

### 6.3 Laravel Configuration

Defansif konfigürasyon — CORS minimal, sadece dev için:

```php
// config/cors.php
<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

    // ⚠️ PRODUCTION: empty array — CORS gerekmez
    // Development: sadece Next.js dev URL
    'allowed_origins' => env('APP_ENV') === 'production'
        ? []
        : ['http://localhost:3000'],

    'allowed_origins_patterns' => [],  // ❌ NEVER use patterns

    'allowed_headers' => [
        'Content-Type',
        'X-Correlation-Id',
        'X-CSRF-Token',
        'Authorization',
    ],

    'exposed_headers' => ['X-Correlation-Id'],

    'max_age' => 600,

    'supports_credentials' => false,  // ⚠️ Cookies proxy üzerinden, CORS ile değil
];
```

### 6.4 Next.js CORS

Next.js'in API route'larında CORS minimal:

```typescript
// frontend/src/app/api/[...path]/route.ts
function corsHeaders(origin: string | null): Headers {
  const headers = new Headers();
  // Aynı origin olduğu için CORS gerekmez ama defense-in-depth:
  const allowedOrigins = [process.env.PUBLIC_APP_URL!];

  if (origin && allowedOrigins.includes(origin)) {
    headers.set('Access-Control-Allow-Origin', origin);
    headers.set('Access-Control-Allow-Credentials', 'true');
  }
  // Eğer origin allowed değilse → header set ETME (browser bloklar)

  return headers;
}
```

### 6.5 Forbidden Patterns

```php
// ❌ NEVER
'allowed_origins' => ['*'],
'supports_credentials' => true,  // Wildcard + credentials = critical CVE
```

```php
// ❌ NEVER (regex bypass possible)
'allowed_origins_patterns' => ['/.*example\.com/'],  // matches "evil-example.com"
```

```php
// ❌ NEVER (Origin reflection)
header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
```

---

## 7. RATE LIMITING

### 7.1 Strategy

İki katmanlı:

**Layer 1: Next.js (per-IP, fast rejection)**
- `/api/auth/login`: 5 attempts / 15 min per IP
- General API: 100 req / minute per IP

**Layer 2: Laravel (per-user, business logic)**
- Login: 5 attempts / 15 min per email
- Order placement: 10 / hour per user
- Password reset: 3 / hour per email

### 7.2 Endpoint Rate Limit Table

| Endpoint | Limit | Window | Key | Response |
|----------|-------|--------|-----|----------|
| `POST /auth/register` | 3 | 1 hour | IP | 429 |
| `POST /auth/login` | 5 | 15 min | email + IP | 429 + lockout 30min |
| `POST /auth/refresh` | 10 | 1 min | user | 429 |
| `POST /auth/logout` | 5 | 1 min | user | 429 |
| `POST /sales/orders` | 10 | 1 hour | user | 429 |
| `POST /sales/orders/{id}/cancel` | 5 | 1 hour | user | 429 |
| `POST /catalog/products` (admin) | 50 | 1 hour | user | 429 |
| `GET /*` (read endpoints) | 200 | 1 minute | user | 429 |
| Global fallback | 100 | 1 minute | IP | 429 |

### 7.3 Laravel Implementation

```php
// app/Providers/RouteServiceProvider.php
RateLimiter::for('login', function (Request $request) {
    $email = $request->input('email');
    return [
        Limit::perMinutes(15, 5)->by($email . '|' . $request->ip()),
    ];
});

RateLimiter::for('place-order', function (Request $request) {
    $userId = $request->attributes->get('user_id');
    return Limit::perHour(10)->by($userId);
});

RateLimiter::for('api-read', function (Request $request) {
    $userId = $request->attributes->get('user_id', $request->ip());
    return Limit::perMinute(200)->by($userId);
});
```

```php
// routes/api/identity.php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/orders', [OrderController::class, 'store'])
    ->middleware(['jwt.auth', 'throttle:place-order']);
```

### 7.4 Account Lockout

Brute force karşı login için ek koruma:

```php
// AuthService brute force protection
if ($this->failedLoginCounter->getCount($email) >= 5) {
    $this->failedLoginCounter->lockAccount($email, ttl: 30 * 60);

    $this->auditLogger->log(new AuditEvent(
        actor: $email,
        actorIp: $ip,
        action: 'security.account_locked',
        resource: 'user:' . $email,
        module: 'identity',
        result: 'failure',
        correlationId: $correlationId,
        metadata: ['lockoutDurationSec' => 1800],
    ));

    throw AccountLockedException::tooManyAttempts();
}
```

> **Not:** Lockout email + IP kombinasyonu üzerinden. Sadece email olursa saldırgan kullanıcının hesabını kasten kilitleyebilir (DoS).

### 7.5 Response Format

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 60
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1714824300
Content-Type: application/json

{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Çok fazla deneme yaptınız. Lütfen 15 dakika sonra tekrar deneyin."
  }
}
```

---

## 8. INPUT VALIDATION & SANITIZATION

### 8.1 Defense Layers

```
1. Frontend validation     (UX, fast feedback — NOT security)
2. Next.js input parsing   (zod schema, basic shape check)
3. Laravel FormRequest     (⭐ AUTHORITATIVE security validation)
4. Domain invariants       (Value Object constructors)
```

### 8.2 FormRequest Standards

```php
final class CreateProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Z0-9\-]+$/'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['sometimes', Rule::in(['TRY', 'USD', 'EUR'])],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => strtoupper(trim((string) $this->input('sku', ''))),
            // ⭐ XSS prevention via strip_tags
            'name' => strip_tags(trim((string) $this->input('name', ''))),
            'description' => $this->input('description')
                ? strip_tags((string) $this->input('description'))
                : null,
        ]);
    }
}
```

### 8.3 SQL Injection Prevention

✅ Always use parameterized queries (Eloquent / Query Builder default)
❌ NEVER raw SQL with string concatenation

```php
// ❌ VULNERABLE
DB::select("SELECT * FROM users WHERE email = '$email'");

// ✅ SAFE — bound parameters
DB::select("SELECT * FROM users WHERE email = ?", [$email]);

// ✅ SAFE — Eloquent
User::where('email', $email)->first();
```

### 8.4 Path Traversal Prevention

Dosya yükleme/indirme yoksa risk düşük, ama:

```php
// ❌ VULNERABLE
$file = $request->input('filename');
return response()->file(storage_path($file));  // ../../../etc/passwd

// ✅ SAFE
$file = basename($request->input('filename'));  // strip directory parts
$allowedFiles = ['report.pdf', 'invoice.pdf'];
abort_unless(in_array($file, $allowedFiles, true), 404);
```

### 8.5 XSS Prevention

**Backend response'larında:**
- JSON response'lar (Laravel default `json_encode`) HTML escape eder
- Error message'lar user input içeriyorsa `htmlspecialchars`

**Frontend:**
- React `{userContent}` syntax otomatik escape eder
- ❌ `dangerouslySetInnerHTML` ASLA user content için kullanılmaz

### 8.6 Mass Assignment Prevention

Eloquent model'lerde:

```php
// ❌ VULNERABLE
final class UserModel extends Model
{
    protected $fillable = ['*'];  // user role'unu da set edebilir!
}

// ✅ SAFE
final class UserModel extends Model
{
    protected $guarded = ['id', 'role', 'created_at'];  // bunlar fillable değil
}
```

> **Bizim mimaride:** Eloquent Model'ler sadece Mapper tarafından kullanılır, FormRequest doğrudan model'e geçmez. Bu attack vektörü zaten kapalı.

---

## 9. SECRETS MANAGEMENT

### 9.1 Secret Inventory

| Secret | Where Used | Rotation | Storage |
|--------|-----------|----------|---------|
| `BACKEND_PROXY_SECRET` | Next.js → Laravel auth | 90 days | Vault / env |
| `JWT_PRIVATE_KEY` (RS256) | Laravel sign | 6 months | Vault, never in repo |
| `JWT_PUBLIC_KEY` | Laravel verify | Pair with private | Repo OK |
| `DB_PASSWORD` | Laravel | 6 months | Vault |
| `REDIS_PASSWORD` | Laravel + Node.js | 6 months | Vault |
| `RABBITMQ_PASSWORD` | Laravel + Node.js | 6 months | Vault |
| `FRANKFURTER_API_KEY` | Currency module | N/A (free) | Vault if needed |
| `APP_KEY` (Laravel encryption) | Laravel | NEVER (data loss) | Vault |

### 9.2 Storage Rules

❌ **ASLA repo'da:**
- `.env`, `.env.production`
- Private keys (`.pem`, `.key`)
- Bcrypt cost / production secrets
- Database passwords

✅ **Storage:**
- Development: `.env.local` (gitignored)
- Production: AWS Secrets Manager / HashiCorp Vault / Doppler / .env on server with 600 perms
- CI/CD: GitHub Secrets / GitLab CI Variables

### 9.3 Detection Strategy

**Pre-commit hook:**

```bash
# .git/hooks/pre-commit (or via husky)
git diff --cached | grep -E "(BEGIN.*PRIVATE KEY|PROXY_SECRET=|DB_PASSWORD=)" && {
  echo "❌ Secret detected in staged changes!"
  exit 1
}
```

**CI scan:**
- `gitleaks` veya `trufflehog` her PR'da çalışır
- Açık secret leak'i merge'i bloklar

### 9.4 Secret Rotation Procedure

Genel pattern:

1. Generate new secret
2. Add new secret as **secondary** (both old + new accepted)
3. Deploy services to use new secret
4. Verify all traffic uses new secret (logs)
5. Remove old secret
6. Audit log: `security.secret_rotation_completed`

---

## 10. SECURITY TESTING CHECKLIST

### 10.1 Required Test Cases (Feature Tests)

Her CRUD modülü için **MİNİMUM** aşağıdaki güvenlik test'leri:

**Authentication:**
- [ ] Auth gerektiren endpoint'e token'sız istek → 401
- [ ] Geçersiz/expired token ile istek → 401
- [ ] Revoked token ile istek → 401

**Authorization (RBAC):**
- [ ] Customer admin endpoint'ine istek → 403
- [ ] Admin customer endpoint'ine istek → 200 (admin her şeye erişebilir)

**Authorization (Resource Ownership / IDOR):**
- [ ] User A, User B'nin order'ını GET → 404
- [ ] User A, User B'nin cart'ını GET → 404
- [ ] User A, User B'nin order'ını cancel → 404

**Input Validation:**
- [ ] SQL injection payload (`' OR 1=1--`) → 422 (validation error)
- [ ] XSS payload (`<script>...</script>`) name field → strip_tags ile temizlenir, başarılı insert
- [ ] Excessive length (10MB string) → 422
- [ ] Negatif fiyat → 422
- [ ] Mass assignment attempt (`role: 'admin'`) → role değişmez

**Rate Limiting:**
- [ ] 6. login attempt → 429 + Retry-After header
- [ ] Order placement 11. istek → 429
- [ ] Lockout sonrası doğru password ile bile login → 423 Locked

**CSRF:**
- [ ] POST request without Origin header → 403
- [ ] POST request with foreign Origin → 403

**Token Replay:**
- [ ] Refresh token kullanıldıktan sonra tekrar kullan → 401 + family revoked
- [ ] Audit log'da `refresh_token_replay` event'i mevcut

**Proxy Bypass:**
- [ ] Direct backend call (X-Proxy-Secret header'sız) → 403
- [ ] Yanlış secret ile call → 403 + audit log

### 10.2 Test Helper Examples

```php
// tests/Feature/Security/IdorTest.php
it('user A cannot view user B order', function () {
    $userA = AuthHelper::createCustomer();
    $userB = AuthHelper::createCustomer();

    $orderB = OrderHelper::createOrderFor($userB);

    $response = $this->actingAs($userA)
        ->getJson("/api/v1/sales/orders/{$orderB->id}");

    $response->assertNotFound();  // 404, NOT 403
});

it('admin can view any order', function () {
    $admin = AuthHelper::createAdmin();
    $customer = AuthHelper::createCustomer();
    $order = OrderHelper::createOrderFor($customer);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/sales/orders/{$order->id}");

    $response->assertOk();
});

it('rejects requests without proxy secret', function () {
    $response = $this->withoutMiddleware([])
        ->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
    // No X-Proxy-Secret header

    $response->assertForbidden();
});
```

### 10.3 Security Test Suite

```bash
# Run only security-focused tests
./vendor/bin/pest tests/Feature/Security
./vendor/bin/pest tests/Feature --filter=security
```

### 10.4 Manual Security Review Checklist (Pre-Deployment)

- [ ] `.env` file `.gitignore`'da
- [ ] Production'da `APP_DEBUG=false`
- [ ] Production'da `APP_ENV=production`
- [ ] Tüm endpoint'ler HTTPS only (HSTS header set)
- [ ] Build sonrası client bundle'da backend URL/secret leak yok
- [ ] Laravel `config:cache` çalıştırılmış
- [ ] CORS production'da `allowed_origins=[]`
- [ ] JWT keys 4096-bit RSA (RS256)
- [ ] Database backups encrypted at rest
- [ ] Log files'da sensitive data yok (sanitizer çalışıyor)
- [ ] Default admin user kaldırılmış / güçlü password
- [ ] Public health endpoint sensitive bilgi sızdırmıyor
- [ ] All third-party dependencies up-to-date (`composer audit`, `npm audit`)

---

## 11. INCIDENT RESPONSE

### 11.1 Detection Triggers

Aşağıdaki audit event'lerden HERHANGİ BİRİ alarm tetikler:

| Event | Threshold | Action |
|-------|-----------|--------|
| `refresh_token_replay` | 1 occurrence | Critical alert, investigate |
| `proxy_bypass_attempt` | 5 / hour | Block IP at firewall |
| `csrf_origin_mismatch` | 10 / hour | Investigate |
| `account_locked` | 50 / hour (system-wide) | Possible coordinated attack |
| `role_denied` | 20 / hour per user | User compromise possible |

### 11.2 Response Playbook

**Token Theft Suspected:**
1. Admin force-revoke all tokens for affected user
2. Notify user via email
3. Audit recent actions for damage assessment
4. If breach confirmed → reset password, mandatory 2FA setup

**Proxy Secret Leak Suspected:**
1. Generate new `BACKEND_PROXY_SECRET`
2. Deploy with dual-secret support (old + new)
3. Monitor logs for old secret usage
4. After 24h with zero old secret usage → remove old

**Database Breach Suspected:**
1. Disconnect Laravel from DB temporarily
2. Take snapshot for forensics
3. Rotate ALL secrets (DB, Redis, JWT keys)
4. Force logout all users
5. KVKK notification within 72h if personal data affected

### 11.3 Incident Audit Trail

Tüm incident response action'ları audit log'lanır:

```php
$this->auditLogger->log(new AuditEvent(
    actor: 'admin:' . $adminId,
    actorIp: $adminIp,
    action: 'security.incident_response',
    resource: 'incident:' . $incidentId,
    module: 'security',
    result: 'success',
    correlationId: $correlationId,
    metadata: [
        'incidentType' => 'token_theft',
        'affectedUsers' => $count,
        'action' => 'force_logout_all',
    ],
));
```

---

## 12. GUEST CART SECURITY (ADR-017)

Login zorunluluğunu kaldırıp guest sepeti destekleyince yeni
saldırı/kötüye-kullanım yüzeyleri oluştu. Bu bölüm alınan önlemleri ve
açık kalan riskleri belgeler.

### 12.1 Session Token Generation

`guest_cart_token` cookie değeri **UUID v4** — `Ramsey\Uuid\Uuid::uuid4()`
kriptografik random kullanır. Tahmin edilemez ve enumerasyona kapalı.
`GuestSessionId` Value Object'i format doğrulamasını
`AbstractUuidValueObject`'ten miras alır; bozuk değerler
`GuestSessionMiddleware`'de regex filter ile düşürülür (yeni session
üretilir).

### 12.2 Cookie Flags

`GuestSessionMiddleware` Set-Cookie header'ını şu flag'lerle yazar:

| Flag | Değer | Neden |
|------|-------|-------|
| `HttpOnly` | true | XSS koruması — JS okuyamaz |
| `Secure` | `app()->environment() !== 'testing'` | Production'da HTTPS-only; test ortamı HTTP olduğundan istisna |
| `SameSite` | Strict | CSRF koruması — cross-site request'lerde gönderilmez |
| `Path` | `/` | Tüm endpoint'ler erişebilsin (BFF mediation'da gerekli) |
| `Max-Age` | 30 gün | DB `expires_at` ile aynı; cleanup job ile uyumlu |

BFF (`frontend/src/app/api/_lib/guest-cart-cookie.ts`) cookie'yi
browser'a Next domain'inde set ederken aynı flag'leri uygular —
`secure` `process.env.NODE_ENV === 'production'`.

### 12.3 Rate Limiting

Guest cart endpoint'leri auth gerektirmediğinden bot/spam riskine
açıktır. Mevcut throttle profilleri yetersiz olabilir; **TODO**:

- `POST /sales/cart/items`: 30/dakika per IP profili eklenmeli
- `GET /sales/cart`: 60/dakika per IP

Şu an global `api` throttle'a güveniyor — incident sonrası kart
endpoint-spesifik profil eklenmeli.

### 12.4 CSRF Protection

`CsrfOriginMiddleware` halen aktif (POST/PATCH/DELETE'te
Origin/Referer header eşleşmesi). SameSite=Strict cookie ek koruma
katmanı; cross-site form post'ları cookie alamaz.

### 12.5 Cleanup & Storage Bloat

Süresi geçmiş guest cart'lar günlük `sales:clean-guest-carts` ile
silinir (routes/console.php → 03:00 daily, withoutOverlapping +
onOneServer). cart_items FK ON DELETE CASCADE ile beraber gider.
Yalnızca `status=active` + `expires_at<=now()` satırlar silinir;
abandoned/converted cart'lar analytics için korunur. Bot saldırısı
sırasında storage şişerse cleanup window'u 1 günden 1 saate çekilebilir
(schedule değişikliği).

### 12.6 Cart Hijack Vektörü

Guest cookie ele geçirilirse saldırgan o sepet üzerinde işlem
yapabilir. Etkisi sınırlı:
- Sipariş veremez (PlaceOrder zorunlu auth)
- Login sonrası merge tetiklendiğinde hijack edilen sepet user'ın
  cart'ına eklenir (saldırgan sepete kötü ürün koyup user'ı login'e
  ittirebilir → user farkında olmadan o ürünleri sipariş edebilir)
- **Mitigation**: SameSite=Strict + HttpOnly + Secure → cookie sızması
  zor; merge sonrası UI banner ("Misafir sepetiniz birleştirildi")
  user'ı uyarır

### 12.7 KVKK / Privacy Perspektifi

Guest cart `guest_session_id` UUID'si **kişisel veri değil** (anonim,
kendi başına bir kişiyi tanımlamaz). Login sonrası `user_id`'ye
bağlandığı andan itibaren KVKK kapsamına girer; merge audit event
(`sales.cart_merged_from_guest`) kaydı bu sınır geçişini belgeler.
Cleanup job 30 gün retention ile veri minimizasyonu prensibine uyar.

---

## ✅ SECURITY IMPLEMENTATION CHECKLIST

Implementation tamam sayılır eğer:

**API Endpoint Exposure (§2):**
- [ ] `ProxyOriginMiddleware` yazıldı, tüm `/api/v1/*` route'larında
- [ ] `BACKEND_PROXY_SECRET` env variable, vault'tan
- [ ] Production docker-compose'da Laravel public port YOK
- [ ] Bypass attempt audit log eder

**Direct Backend Access (§3):**
- [ ] Frontend'de `LARAVEL_API_URL` `NEXT_PUBLIC_*` prefix'siz
- [ ] Catch-all proxy + dedicated auth route handler'lar yazıldı
- [ ] CI'da backend URL leak detection script
- [ ] Production deployment doc'unda network isolation belirtilmiş

**Token Security (§4):**
- [ ] httpOnly + Secure + SameSite=Strict cookies
- [ ] Refresh token DB'de hashed
- [ ] Token family + replay detection implement edilmiş
- [ ] 5 saniye grace period
- [ ] CSRF Origin middleware
- [ ] JWT blacklist (Redis)
- [ ] RS256 algorithm

**Authorization (§5):**
- [ ] RoleMiddleware
- [ ] `assertCanBeViewedBy` pattern Order ve Cart'ta
- [ ] IDOR test'leri her resource için
- [ ] Authorization matrix dokümante

**CORS (§6):**
- [ ] Production'da `allowed_origins=[]`
- [ ] Wildcard yok, regex pattern yok
- [ ] `supports_credentials=false`

**Rate Limiting (§7):**
- [ ] Login: 5/15min per email+IP
- [ ] Order: 10/hour per user
- [ ] Account lockout 30min after 5 fails
- [ ] Retry-After header doğru

**Validation (§8):**
- [ ] Tüm FormRequest'lerde `prepareForValidation` strip_tags
- [ ] Mass assignment guard'lar set
- [ ] SQL injection test'leri pass

**Secrets (§9):**
- [ ] Pre-commit hook
- [ ] CI gitleaks scan
- [ ] `.env*` files `.gitignore`'da
- [ ] Rotation procedure dokümante

**Testing (§10):**
- [ ] Security test suite (`tests/Feature/Security/`)
- [ ] Tüm checklist test'leri yazıldı
- [ ] CI'da çalışıyor

---

**Last Updated:** 2026-05-04
**Version:** 1.0
**OWASP Reference:** Top 10 (2021), API Security Top 10 (2023)
