# Dehasoft Test Projesi

10 günlük teknik test — basit e-ticaret (auth + ürün + sepet + sipariş + multi-currency).

**Stack:** Laravel 11 (PHP 8.4) · Next.js 16 (React 19, TS 5.9) · Node.js 20 · PostgreSQL 15 · Redis 7 · RabbitMQ 3.13 · Elasticsearch 8.13 · Kibana 8.13

**Mimari:** Onion + CQRS + DDD (Lite) + FSD (frontend) + Decoupled RabbitMQ Logging

---

## Monorepo Yapısı

```
dehasoft-test/
├── backend/                # Laravel 11 API (Onion + CQRS + DDD-Lite)
├── frontend/               # Next.js 16 (FSD + BFF proxy)
├── log-service/            # Node.js consumer (RabbitMQ → Elasticsearch)
├── infrastructure/
│   ├── rabbitmq/           # definitions.json + rabbitmq.conf
│   ├── elasticsearch/      # component+index templates + ILM policies
│   └── postgres/init/      # parallel test DB
├── scripts/
│   ├── check-backend-leak.sh   # frontend bundle leak gate
│   └── setup-es.sh             # idempotent ES template/ILM yükleyici
├── docs/                   # Mimari dokümanları
└── .github/workflows/      # backend / frontend / log-service CI
```

---

## Quick Start

### 1) Repoyu klonla ve env dosyalarını hazırla

```bash
git clone <repo> dehasoft-test
cd dehasoft-test
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env.local
```

`.env`, `backend/.env`, `frontend/.env.local` üçünde `BACKEND_PROXY_SECRET`
**aynı** olmalı. `APP_KEY` ve `JWT_SECRET` boşsa local için aşağıdaki komutla doldur:

```bash
docker compose run --rm backend php artisan key:generate
docker compose run --rm backend php -r 'echo "JWT_SECRET=" . bin2hex(random_bytes(32)) . PHP_EOL;'
```

### 2) Servisleri başlat

```bash
docker compose up -d
```

8 servis ayağa kalkar:

| Servis | Port | Açıklama |
|--------|------|----------|
| postgres | 5432 | 4 schema: `identity`, `catalog`, `sales`, `currency` |
| redis | 6379 | Cache + queue + JWT blacklist |
| rabbitmq | 5672 / 15672 | Topology declarative (definitions.json) — UI: admin/admin |
| elasticsearch | 9200 | Log storage |
| kibana | 5601 | Log görselleştirme |
| backend | 8000 | Laravel API (nginx + php-fpm) |
| queue-worker | — | `queue:work --queue=logs,default` |
| frontend | 3000 | Next.js BFF + UI |
| log-service | — | 3 consumer (audit/app/access) |

### 3) Elasticsearch template + ILM yükle (ilk kalkışta bir kez)

```bash
./scripts/setup-es.sh
```

Idempotent — sonradan template/ILM güncellenirse tekrar çalıştırılır.

### 4) Erişim

- **Frontend:** http://localhost:3000
- **API:** http://localhost:8000/api/v1
- **RabbitMQ UI:** http://localhost:15672 (admin / admin)
- **Kibana:** http://localhost:5601 (index pattern: `logs-*`)

---

## Mimari Özet

### Backend — Onion + CQRS + DDD (Lite)

```
Domain ← Application ← Infrastructure → Presentation (HTTP)
```

- **Domain**: Aggregate roots (Cart, Order), Value Objects (Money, Email, PlainPassword), Domain Services
- **Application**: CommandBus + QueryBus (PSR-11 ContainerInterface ile bağlanan handler map'leri)
- **Infrastructure**: Eloquent repository implementasyonları, RabbitMqPublisher, JWT issuer (HS256)
- **Presentation**: Slim controller'lar (yalnızca request → command/query çevirisi)

PostgreSQL 4 ayrı schema kullanır; `identity`, `catalog`, `sales`, `currency` —
modüler sınırı veritabanı seviyesinde de korur.

### Frontend — FSD + BFF Proxy

```
app → pages-ui → widgets → features → entities → shared
```

- **BFF pattern:** Tarayıcı **doğrudan** Laravel'e gitmez. `/api/*` Next.js
  catch-all route handler'ı (`src/app/api/[...path]/route.ts`) tüm istekleri
  proxy'ler; `X-Proxy-Secret` header'ını ekler ve `st_at`/`st_rt` cookie'leri
  ile JWT'leri server-side tutar.
- **Server-only fence:** `import 'server-only'` + ESLint `no-restricted-imports`
  — server-fetch helper'ı asla client bundle'a sızmaz. `scripts/check-backend-leak.sh`
  her CI run'da `.next/static/*.js` içinde backend URL/secret arar.

### Logging — Decoupled Pipeline

```
Laravel ──┬─ AuditLogger ──sync─┐
          │                     ├─→ logs.exchange (topic) ─┬─→ logs.audit.queue   ─→ log-service ─→ ES (logs-audit-*)
          ├─ AppLogger ─Redis───┤                          ├─→ logs.app.queue     ─→ log-service ─→ ES (logs-app-*)
          └─ AccessMiddleware──Redis ──→ Laravel queue:work ┘ └─→ logs.access.queue ─→ log-service ─→ ES (logs-access-*)
                                                                                                            │
                                                                                          parse/index fail ─┴─→ logs.dlx → logs.dlq
```

- **Audit** (sync): publisher confirm + mandatory; başarısız → `audit-fallback.log` → `php artisan audit:replay-fallback` zincir tamamlar.
- **App / Access** (async): Redis queue üstünden `queue:work` worker, sonra fire-and-forget AMQP publish.
- **ILM:** audit 90d, access 30d, app 14d (per-kategori retention).

### Güvenlik

- **JWT HS256** + refresh token rotation + replay detection (5s grace, family-revoke on reuse)
- **Redis JWT blacklist** logout'ta
- **IDOR pattern:** Yetkisi olmayan kullanıcı **404** alır (403 değil) — anti-enumeration
- **Rate limit:** Login + register + order endpoint'leri için Redis tabanlı throttle
- **CORS + Origin guard:** `BACKEND_PROXY_SECRET` ile `hash_equals` doğrulama
- **Bcrypt cost 12** (testte 4) prod için, JWT cookies HttpOnly+Secure+SameSite=Lax

---

## Test

### Backend

```bash
cd backend
docker compose exec backend vendor/bin/pest
# veya host'ta:
vendor/bin/pest
```

Pest 3.x test suite — 4 testsuite (Domain, Application, Infrastructure, Feature),
parallel testler için `RefreshDatabase`. CI'de PostgreSQL service container ile koşar.

### Frontend

```bash
cd frontend
pnpm lint && pnpm build
bash ../scripts/check-backend-leak.sh
```

### Log Service

```bash
cd log-service
npm run typecheck && npm run lint && npm run build
```

---

## CI/CD

`.github/workflows/`:

- **backend.yml** — PHP 8.4 + PostgreSQL 15 + Redis 7 service container, Pest suite
- **frontend.yml** — Node 20 + pnpm 9, lint + build + leak gate
- **log-service.yml** — Node 20 + npm, typecheck + lint + build

Path-filter ile sadece ilgili klasör değişince tetiklenir.

---

## Doküman Haritası

| Dosya | İçerik |
|-------|--------|
| `CLAUDE.md` | Glob patterns, hızlı kod kalıpları, sık hatalar |
| `DECISIONS.md` | Mimari ADR'ları (15 karar) |
| `docs/PROJECT_STRUCTURE.md` | Klasör yapısı, modüller, FSD, dependency rules |
| `docs/DATABASE_SCHEMA.md` | PostgreSQL şemaları, tablolar, indexler |
| `docs/CODING_STANDARDS.md` | Kod template'leri (VO, Entity, Handler, Repo, Controller) |
| `docs/LOGGING_SERVICE.md` | RabbitMQ + Node.js + ES pipeline detayı |
| `docs/SECURITY.md` | 5 güvenlik tehdidi + mitigation + test checklist |

---

## Troubleshooting

### Backend `php artisan migrate` "no unique constraint matching given keys"
`identity.refresh_tokens.replaced_by_token_id` self-FK'i `Schema::create` içinde
tanımlanırsa Postgres unique sorunlu. ALTER TABLE ile dışarı alınmıştır
(database/migrations/identity/...).

### Frontend build "BACKEND_PROXY_SECRET is required"
`frontend/.env.local` yok ya da boş. `.env` ile aynı secret'ı kopyala.

### `vendor/bin/pest` "rate limit exceeded" (12+ orders)
TestCase `setUp`'unda `Cache::flush()` çağrılır; buna rağmen ardışık testler
arasında sızıyorsa testing env'de rate-limit middleware bypass edilmiş olmalı.

### Queue worker "unhealthy"
`queue-worker` HTTP listener taşımaz; compose dosyasında `healthcheck.disable: true`
ile devre dışıdır. "unhealthy" görüyorsan compose dosyanı güncelle.

### Log mesajları Elasticsearch'e gitmiyor
1. RabbitMQ Management UI'da `logs.exchange`'e mesaj akıyor mu kontrol et.
2. `logs.{audit,app,access}.queue` consumer sayısı **3** olmalı (log-service).
3. `logs.dlq` doluyorsa: parse hatası ya da ES connection — `docker logs dehasoft-log-service`.
4. ES template/ILM yüklü mü? `curl localhost:9200/_index_template/logs-audit | jq`.

---

## Lisans

Test projesi — production kullanımı için tasarlanmamıştır.
