# 🎯 Sistem Tanıtımı

> **E-Ticaret Demo Projesi** — Onion Architecture + CQRS + DDD-Lite + FSD + Decoupled Logging mimarisinde Laravel 11 + Next.js 16 e-commerce demo. Misafir sepet, multi-currency, JWT-on-cookie BFF, RabbitMQ→Elasticsearch logging, audit hash chain.

---

## 1) Vizyon ve Kapsam

| Boyut | Değer |
|-------|-------|
| Hedef | Production-ready mimari + akış dokümanlı CV / mülakat case |
| Temel akış | Misafir → ürün listesi → sepet → kayıt/login (cart merge) → adres → checkout → sipariş |
| İkincil akış | Admin: ürün yönetimi + sistem ayarları + base currency değişimi (atomic reprice) |
| Anahtar mimari kararlar | 18 ADR (DECISIONS.md) |
| Code review | 10 phase report — `docs/CODE_REVIEW_REPORT.md` |

---

## 2) Stack Versiyon Tablosu

| Katman | Teknoloji | Sürüm |
|--------|-----------|-------|
| Backend Runtime | PHP | 8.4 |
| Backend Framework | Laravel | 11.x |
| Backend Test | Pest | 3.x |
| Frontend Runtime | Node.js | 20 |
| Frontend Framework | Next.js (App Router) | 16 |
| UI Library | React | 19 |
| Type System | TypeScript | 5.9 |
| Stiller | Tailwind | 3.x |
| State | Zustand + React Query | 5 |
| Form / Validation | Zod | 3 |
| Database | PostgreSQL | 15 |
| Cache + Queue | Redis | 7 |
| Message Broker | RabbitMQ | 3.13 |
| Log Store | Elasticsearch | 8.13 |
| Log UI | Kibana | 8.13 |
| Container | Docker Compose | v2 |

---

## 3) Mimari Genel Bakış

### 3.1 Backend — Onion + CQRS + DDD-Lite

```
┌────────────────────────────────────────────────────────────┐
│                       Presentation                         │
│   (Controller, FormRequest, Resource, OpenAPI attribute)   │
└────────────────────────┬───────────────────────────────────┘
                         │ Bus dispatch (Command / Query)
┌────────────────────────▼───────────────────────────────────┐
│                       Application                          │
│   (Handler, DTO, Validator) — orchestration only           │
└────────────────────────┬───────────────────────────────────┘
                         │ Domain method invocation
┌────────────────────────▼───────────────────────────────────┐
│                          Domain                            │
│   Entity, ValueObject, Domain Service, Repository iface    │
│   (zero framework dependency, pure PHP)                    │
└────────────────────────┬───────────────────────────────────┘
                         │ Repository contract
┌────────────────────────▼───────────────────────────────────┐
│                      Infrastructure                        │
│   Eloquent Model, RepositoryImpl, Service Adapter          │
│   (Laravel-coupled, isolated to this layer)                │
└────────────────────────────────────────────────────────────┘
```

- **Modül başına tam stack**: `Domain/<Module>/`, `Application/<Module>/`, `Infrastructure/<Module>/`, `Presentation/<Module>/`.
- **Bus**: Command-Bus + Query-Bus (Laravel container resolved). Handler signature `__invoke(Command $cmd)`.
- **Middleware**: `BootGuard` (PHP version), `RequireProxySecret` (BFF→backend), `JwtAuth` / `OptionalJwt` / `RoleAuth`, `GuestSession` (cookie-based UUID), domain-specific throttles.
- **Audit hash chain**: `system.audit_logs` her satır prev hash'i + payload SHA256, tamper-evident.

### 3.2 Frontend — FSD + BFF Proxy

```
app/                      # Next.js routes (page.tsx, layout.tsx)
features/<domain>/        # Domain-specific UI/state (cart, products, addresses)
entities/                 # Type contracts mirroring backend DTOs
shared/                   # api-client, ui kit, utils
processes/                # Cross-feature flows (checkout, auth bootstrap)
```

- Cookie tabanlı session: `st_at` (15 dk JWT), `st_rt` (30 d, path=/api/auth/refresh-narrowed), `guest_cart_token` (30 d UUID).
- Browser → Next.js BFF → Laravel — `BACKEND_PROXY_SECRET` ile shared secret.
- Backend public exposure yok — sadece `:8000` localhost; production'da nginx ingress arkasında.

### 3.3 Logging Pipeline

```
Application
    │ (audit/app/access event)
    ▼
RabbitMQ (3 queue: audit/app/access + DLQ)
    │ (Node.js consumer)
    ▼
log-service
    │ (validate + transform + Elasticsearch bulk)
    ▼
Elasticsearch (logs-audit / logs-app / logs-access ILM-managed)
    │
    ▼
Kibana (data view: logs-*)
```

ADR-002 — backend asla doğrudan ES'ye yazmaz. RabbitMQ unavailable ise audit event Postgres fallback table'a düşer + `logging:replay-audit-fallback` cron'u tekrar publish eder. Logging delays user request **ASLA**.

---

## 4) Modül Matrisi

| Modül | Sorumluluk | Public API |
|-------|------------|-----------|
| **Iam** | Kullanıcı, rol, JWT, refresh token | `/api/auth/*`, `/api/identity/*` |
| **Sales** | Sepet (user + guest), sipariş, adres | `/api/sales/cart`, `/api/sales/orders` |
| **Catalog** | Ürün, stok, fiyat, SKU | `/api/catalog/products` |
| **Currency** | Exchange rate, sync, snapshot | `/api/currency/rates` |
| **System** | Setting, base currency, audit | `/api/system/settings`, `/api/admin/system/*` |
| **Identity** | Adres rehberi, location validation | `/api/identity/addresses`, `/api/locations/*` |
| **Cms** | Public page, slug routing | minimal — placeholder |
| **Shared** | DTO, Bus, Auth helpers, exceptions | n/a (cross-cutting) |

---

## 5) Database — 5 Schema, 11 Tablo

```
identity                 system                  catalog
├── users                ├── system_settings     └── products
├── refresh_tokens       ├── audit_logs
└── addresses            └── audit_fallback

currency                 sales
├── exchange_rates       ├── carts
                         ├── cart_items
                         └── orders (snapshot fields: address JSONB, fxRate, displayTotal)
                         └── order_items
```

- Her schema kendi migration namespace'inde.
- Cross-schema FK (`sales.orders.user_id → identity.users.id`) qualified isimle.
- ADR-007: tüm timestamp UTC; presentation katmanı user TZ'ye dönüştürür.
- ER diagram: `docs/DATABASE_SCHEMA.md`.

---

## 6) ADR Özet Tablosu

| # | Başlık | Status | Etki |
|---|--------|--------|------|
| 001 | Onion + CQRS + DDD-Lite | Accepted | Tüm backend |
| 002 | Decoupled Logging via RabbitMQ | Accepted | log-service |
| 003 | JWT-on-Cookie BFF (st_at, st_rt) | Accepted | auth |
| 004 | Refresh Token Rotation + Family Revoke | Accepted | iam |
| 005 | Audit Hash Chain | Accepted | system |
| 006 | Schema-per-Module | Accepted | DB |
| 007 | UTC Storage + TZ Convert at Edge | Accepted | system |
| 008 | OpenAPI as Code (PHP attributes) | Accepted | swagger |
| 009 | Idempotency-Key Pattern (orders) | Accepted | sales |
| 010 | Order Snapshot — fx + display | Accepted | sales |
| 011 | Optimistic Locking on Stock | Accepted | catalog |
| 012 | Soft-Delete + Audit Retention 6y | Accepted | identity, sales |
| 013 | Rate Limiting per-route Tiered | Accepted | http |
| 014 | Frontend FSD + BFF Proxy | Accepted | frontend |
| 015 | Pest 3 + RefreshDatabase Strategy | Accepted | tests |
| **016** | **Single Base Currency (TRY)** | **Accepted** | **currency, catalog, sales** |
| **017** | **Guest Cart with Session Cookie** | **Accepted** | **sales, frontend** |
| **018** | **Address Management + Snapshot** | **Accepted** | **identity, sales** |

> ADR-016/017/018 bu case'in son sprint'inde eklendi. Detaylar: [DECISIONS.md](../DECISIONS.md).

---

## 7) Endpoint Envanteri (34 toplam)

| Grup | Endpoint sayısı | Auth |
|------|-----------------|------|
| Auth | 5 | none / cookie |
| Identity (me, addresses) | 8 | user (cookie) |
| Locations (TR cities/districts) | 2 | none |
| Catalog (products) | 6 | mixed (admin write) |
| Sales (cart) | 5 | optional (user/guest) |
| Sales (orders) | 4 | user |
| System (settings) | 4 | mixed (admin write) |

Tam liste + request/response schemaları: [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) + Swagger UI (http://localhost:8000/api/documentation).

---

## 8) Yeni Özellik Highlights (Son Sprint)

### ADR-016 — Single Base Currency
- Admin paneli currency picker kaldırıldı; tüm fiyatlar `system.base_currency` altında.
- `base_currency` değişimi → atomic transaction:
  1. `currency.exchange_rates` son rate fetch (max 7 gün stale → fail)
  2. Tüm `catalog.products.price` reprice (eski_base → yeni_base)
  3. Tüm `sales.carts WHERE status=active` `abandoned` (mixed currency koruma)
  4. Setting update + Redis cache invalidate
  5. Audit event: `system.base_currency_changed { rate, productsUpdated, cartsAbandoned }`
- Frontend: SweetAlert warning + onay → success modal özet ile.

### ADR-017 — Guest Cart
- `guest_cart_token` HttpOnly cookie (30 gün, UUID).
- `optional.jwt + guest.session` middleware sıralaması: login varsa token öncelikli, yoksa cookie issue.
- Login sırasında **automatic merge**: aynı SKU varsa quantity max'lanır (cap respect), priceSnapshot user'inkini koruyup guest'inkiyle override etmez.
- Cleanup job: `sales:clean-guest-carts` 30+ gün eski cart'ları siler (production cron).

### ADR-018 — Address Management
- 3'lü konum doğrulama: TurkeyLocationsService (81 il + 957 ilçe statik JSON).
- Default delivery + default billing **ayrı**; sadece bir tane olabilir (UNIQUE partial index).
- IDOR koruması: `findByIdAndUser(id, userId)` — ait değilse 404 (anti-enumeration).
- Order place'de `deliveryAddressId` zorunlu → snapshot olarak `orders.delivery_address` JSONB'ye yazılır. Adres silinse bile sipariş geçmişi korunur.

### Code Review Phase 1-10
- 5 KRİTİK + 4 ORTA bulgu fix.
- Production hazırlığı %75 → ~%95.
- Detay: [CODE_REVIEW_REPORT.md](./CODE_REVIEW_REPORT.md).

---

## 9) Test Metrikleri

| Suite | Durum |
|-------|-------|
| Pest test sayısı | **124 PASS / 0 FAIL** |
| Assertion | 1065 |
| Süre (parallel) | ~5 saniye |
| Coverage tahmini | ~%80 |

### Test Pyramid

```
  Feature/E2E       ~70 (Auth, Cart, Address, Order, Setting, Currency)
  Application Unit  ~25 (Handler tests w/ Mockery)
  Domain Unit       ~29 (Pure PHP, no framework boot)
                   ───
                    124
```

### Key Test Files
- `tests/Feature/Sales/CurrencySnapshotTest.php` — ADR-010 immutability E2E (kur sonradan değişse bile snapshot frozen)
- `tests/Feature/Sales/GuestCartFlowTest.php` — ADR-017 cookie + merge
- `tests/Feature/Identity/AddressManagementTest.php` — ADR-018 IDOR + default toggle
- `tests/Feature/System/BaseCurrencyChangeTest.php` — ADR-016 atomic reprice
- `tests/Unit/Domain/Sales/OrderTest.php` — Order entity invariants

---

## 10) Güvenlik Snapshot

| Kontrol | Durum |
|---------|-------|
| HttpOnly + Secure + SameSite cookie | ✓ tüm session/guest cookie |
| Refresh token path narrowing | ✓ `/api/auth/refresh-narrowed` |
| BACKEND_PROXY_SECRET (BFF) | ✓ enforce + .env required gate |
| JWT signature + exp | ✓ 15 dk lived |
| Rate limit (login) | ✓ 5/15dk per IP+email |
| Rate limit (cart write) | ✓ 30/dk per user/guest/IP |
| Rate limit (order) | ✓ 5/dk per user |
| IDOR (address, order) | ✓ findByIdAndUser pattern |
| XSS (title, addressLine) | ✓ strip_tags + Content-Type guard |
| Audit hash chain | ✓ tamper-evident |
| Password hashing | ✓ bcrypt cost 12 |
| Production env validation gate | ✓ scripts/validate-prod-env.sh |
| APP_DEBUG=true production block | ✓ (Code Review fix) |

Tam threat model + mitigation matrix: [SECURITY.md](./SECURITY.md).

---

## 11) Production Hazırlığı

| Boyut | Durum |
|-------|-------|
| Test coverage | ✓ 124 PASS |
| Production env template | ✓ `backend/.env.production.example` |
| Pre-deploy validation script | ✓ `scripts/validate-prod-env.sh` |
| DB backup script | ✓ `scripts/backup-db.sh` (rolling 30d + monthly + S3 sync) |
| Logging & monitoring | ✓ ES/Kibana + audit chain |
| Cleanup jobs (cron) | ✓ 3 scheduler entry |
| Smoke scripts | ✓ guest-cart + address |
| ES index template + ILM | ✓ `scripts/setup-es.sh` |
| Open todo | TLS termination + ingress nginx config (deployment-spesifik) |

---

## 12) Doküman Haritası

| Doküman | Açıklama |
|---------|----------|
| [README.md](../README.md) | Proje girişi + quick start |
| [KURULUM.md](./KURULUM.md) | Local kurulum adım adım |
| [KULLANIM.md](./KULLANIM.md) | Customer/Admin akış + smoke + Kibana sorgu |
| [TANITIM.md](./TANITIM.md) | (Bu dosya) — sistem mimari özeti |
| [DECISIONS.md](../DECISIONS.md) | 18 ADR detay |
| [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) | Endpoint inventory + sözleşme |
| [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) | Tablo yapısı + ER |
| [PROJECT_STRUCTURE.md](./PROJECT_STRUCTURE.md) | Modül layout |
| [CODING_STANDARDS.md](./CODING_STANDARDS.md) | Naming + conventions |
| [SECURITY.md](./SECURITY.md) | Threat model + mitigations |
| [LOGGING_SERVICE.md](./LOGGING_SERVICE.md) | RabbitMQ → ES pipeline |
| [FRONTEND_GUIDE.md](./FRONTEND_GUIDE.md) | FSD + page envanter |
| [CODE_REVIEW_REPORT.md](./CODE_REVIEW_REPORT.md) | Phase 1-10 audit raporu |

---

## 13) Repo İstatistikleri

| Metrik | Değer |
|--------|-------|
| Backend modül sayısı | 8 |
| Migration sayısı | 17 |
| Controller sayısı | ~16 |
| Handler sayısı (Application) | ~35 |
| Domain entity | ~14 |
| Frontend page | 13 |
| API endpoint | 34 |
| Pest test | 124 (1065 assertion) |
| ADR | 18 |
| Doc dosyası | 13 (root README + DECISIONS + 11 docs/) |
| Docker servis | 9 |

---

## 14) Hızlı Erişim

```
Demo:           http://localhost:3030
Backend:        http://localhost:8000
Swagger UI:     http://localhost:8000/api/documentation
Kibana:         http://localhost:5601
RabbitMQ UI:    http://localhost:15672  (admin/admin)

Test hesapları:
  admin@example.test      / Admin123!
  customer@example.test   / Customer123!

Kurulum:        docs/KURULUM.md
Kullanım:       docs/KULLANIM.md
Mimari kararlar: DECISIONS.md
Code review:    docs/CODE_REVIEW_REPORT.md
```

---

> **Son notlar:** Bu sistem mülakat / case incelemesi için hazırlanmıştır. Production deploy notları KURULUM § 12 ve README "Production Deploy" bölümünde. Soru / geri bildirim için: bu repo'ya issue açılabilir.
