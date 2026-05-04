# 🚀 Dehasoft Test Projesi - Claude Code Bootstrap Prompt

> **NASIL KULLANILIR:** Aşağıdaki promptu Claude Code'a kopyala-yapıştır olarak ver. Promptun başında **5 doküman dosyasını da `@docs/PROJECT_STRUCTURE.md` gibi referans olarak göster.** Claude Code dosyaları okuyup adım adım projeyi ayağa kaldıracak.

---

## 📁 ÖN HAZIRLIK (sen yapacaksın)

Claude Code başlamadan önce şu yapıyı kur:

```bash
mkdir dehasoft-test && cd dehasoft-test
git init
mkdir docs .claude

# CLAUDE.md ROOT'a koy — Claude Code her oturumda otomatik okur
cp ~/Downloads/CLAUDE.md ./CLAUDE.md

# DECISIONS.md ROOT'a koy — ADR log
cp ~/Downloads/DECISIONS.md ./DECISIONS.md

# 5 ana doküman docs/ altına
cp ~/Downloads/PROJECT_STRUCTURE.md docs/
cp ~/Downloads/DATABASE_SCHEMA.md docs/
cp ~/Downloads/CODING_STANDARDS.md docs/
cp ~/Downloads/LOGGING_SERVICE.md docs/
cp ~/Downloads/SECURITY.md docs/

# .claude/settings.json — Claude Code config (token optimization)
cat > .claude/settings.json <<'EOF'
{
  "auto_compact_threshold": 0.7,
  "exclude_patterns": [
    "**/node_modules/**",
    "**/vendor/**",
    "**/.next/**",
    "**/dist/**",
    "**/storage/logs/**",
    "**/storage/framework/cache/**",
    "**/.git/**"
  ]
}
EOF

# .gitignore
cat > .gitignore <<'EOF'
.env*
!.env.example
node_modules/
vendor/
.next/
dist/
storage/logs/
storage/framework/cache/
.DS_Store
*.log
EOF

git add . && git commit -m "chore: initial docs + CLAUDE.md + decisions"

# Claude Code başlat
claude
```

İlk Claude Code mesajı olarak aşağıdaki **MASTER PROMPT**'u kullan.

> 💡 **Token tasarrufu için:** TOKEN_OPTIMIZATION.md'yi sen okumuş ol — Claude Code'a vermek zorunda değilsin. O dosya **senin için** oturum yönetimi rehberi.

---

## 🎯 MASTER PROMPT (Claude Code'a yapıştırılacak)

````
# Dehasoft Test Projesi - Bootstrapping

Sen Senior Software Architect rolündesin.

**ÖNCE şunları oku:**
1. @CLAUDE.md - Project memory, glob patterns, forbidden patterns, en çok yapılan hatalar
2. @DECISIONS.md - 15 mimari kararın tarihi (verilen kararı sorgulama)

**SONRA dokümanları SİNDİR:**
3. @docs/PROJECT_STRUCTURE.md - Klasör yapısı, modüller, FSD
4. @docs/DATABASE_SCHEMA.md - PostgreSQL şemaları, tablolar
5. @docs/CODING_STANDARDS.md - Kod template'leri
6. @docs/LOGGING_SERVICE.md - RabbitMQ + Node.js + ES
7. @docs/SECURITY.md - 5 güvenlik tehdidi + mitigation

Bu 7 doküman projenin TEK GERÇEK KAYNAĞIDIR.

## TEMEL KURALLAR

- **CLAUDE.md'deki kurallar tüm dokümanların üstündedir.**
- **Bu dokümanlardan SAPMA YOK.** Doküman ile çelişirse dokümanın söylediğini yap.
- **Bir şey belirsizse SOR, varsayım yapma.**
- **Tüm kod CODING_STANDARDS.md'deki template'lere harfiyen uymalı**
- **Her commit anlamlı bir adımı tamamlamalı**
- **Türkçe açıklama, İngilizce kod**
- **Token bilinçli ol:** Aynı dosyayı 2+ kez okuma, full test suite yerine scoped test, Edit > Write
- **Mimari karar verirken:** önce DECISIONS.md'ye bak. Yeni karar gerekirse benimle tartış, ADR ekle.

## TEKNİK STACK ÖZET

- **Backend:** Laravel 11 + PHP 8.2 + PostgreSQL 15 + Redis 7
- **Frontend:** Next.js 15 (App Router) + TypeScript + Tailwind + shadcn/ui
- **Logging:** Node.js 20 + TypeScript + amqplib + @elastic/elasticsearch
- **Infrastructure:** RabbitMQ 3.13, Elasticsearch 8.13, Docker Compose
- **Testing:** Pest 3.x (backend), Vitest (frontend + log-service)
- **Architecture:** Onion + CQRS + DDD (Lite) — bkz. CODING_STANDARDS.md

## SPRINT 0 - BOOTSTRAPPING (BU SPRINT'TEKİ TÜM TASK'LARI YAP)

Bu konuşmada ŞU sırayla ilerleyeceğiz. Her adım bittikten SONRA ilerle:

### TASK 0.1 — Plan Onayı
Önce dokümanları okuduğunu özetle. Mimariyi 1 paragrafla anlat (Onion + CQRS + DDD nasıl uygulanacak). Bu özette:
- Modüller (Identity, Catalog, Sales, Currency)
- Layer'lar (Domain, Application, Infrastructure, Presentation)
- Proxy katmanı (Next.js BFF)
- Log servisi mimarisi
Net olmalı.

Bekle — onay verince devam et.

### TASK 0.2 — Monorepo Skeleton

Aşağıdaki kök yapıyı yarat (henüz dosya içeriği yok, sadece klasörler ve placeholder README'ler):

```
dehasoft-test/
├── backend/                 # Laravel
├── frontend/                # Next.js
├── log-service/             # Node.js consumer
├── infrastructure/
│   ├── elasticsearch/
│   │   ├── templates/
│   │   ├── ilm-policies/
│   │   └── bootstrap.sh
│   └── rabbitmq/
│       ├── definitions.json
│       └── rabbitmq.conf
├── docs/                    # Already exists
├── scripts/                 # CI helpers
├── docker-compose.yml       # Root level, all services
├── docker-compose.production.yml
├── .gitignore               # Root
├── .editorconfig
└── README.md
```

Her servis klasörünün altına minimal README ekle ("This service: <description>. See docs/PROJECT_STRUCTURE.md for details.").

`.gitignore`'a şunları ekle: node_modules, vendor, .env*, .env.local, storage/logs, .next, dist, .DS_Store

İlk commit: "chore: monorepo skeleton".

Bekle — devam onayı al.

### TASK 0.3 — docker-compose.yml (Tüm Servisler)

Root `docker-compose.yml` dosyasını yaz. İçinde:
- postgres:15 (port 5432, persistent volume)
- redis:7-alpine (port 6379)
- rabbitmq:3.13-management-alpine (5672 + 15672, definitions.json mount)
- elasticsearch:8.13.0 (port 9200, single-node, security disabled for dev)
- kibana:8.13.0 (port 5601)
- backend (Laravel container, port 9000 internal)
- frontend (Next.js, port 3000)
- log-service (Node.js, no port)
- queue-worker (Laravel queue:work container)

Healthcheck'ler eklenmeli. Network: tek bir bridge network (`dehasoft-net`).

Hala Dockerfile'lar yok — onları sonraki task'larda yazacağız.

Bekle.

### TASK 0.4 — Backend (Laravel) Bootstrap

```bash
cd backend
composer create-project laravel/laravel . "^11.0"
```

Sonra:
- `composer require ramsey/uuid` (UUID v4 generation)
- `composer require tymon/jwt-auth` (JWT)
- `composer require php-amqplib/php-amqplib` (RabbitMQ)
- `composer require predis/predis` (Redis)
- `composer require --dev pestphp/pest pestphp/pest-plugin-laravel`
- `composer require --dev mockery/mockery`

`composer.json`'da PSR-4 autoloading'i CODING_STANDARDS.md'deki yapıya göre düzenle:

```json
"autoload": {
  "psr-4": {
    "App\\": "app/",
    "Domain\\": "src/Domain/",
    "Application\\": "src/Application/",
    "Infrastructure\\": "src/Infrastructure/",
    "Presentation\\": "src/Presentation/"
  }
}
```

`src/{Domain,Application,Infrastructure,Presentation}/{Identity,Catalog,Sales,Currency}/...` klasör hiyerarşisini PROJECT_STRUCTURE.md'deki gibi yarat. Her modülde Contracts, Entities, ValueObjects, Exceptions, Services klasörleri (Domain için) — yine doküman ne diyorsa.

`Pest.php` configuration. `phpunit.xml` Pest için ayarla.

İlk commit: "feat(backend): laravel skeleton + module folders".

Bekle.

### TASK 0.5 — Backend: Shared Kernel + Base Classes

CODING_STANDARDS.md'deki tüm abstract base class'ları yaz:
- `Domain/Shared/Abstracts/AbstractValueObject`
- `Domain/Shared/Abstracts/AbstractUuidValueObject`
- `Domain/Shared/Abstracts/AbstractEntity`
- `Domain/Shared/Contracts/ValueObjectInterface`
- `Domain/Shared/Contracts/RepositoryInterface`
- `Domain/Shared/Constants/HttpStatus`
- `Domain/Shared/Exceptions/{DomainException, NotFoundException, ValidationException, BusinessRuleException, InfrastructureException}`
- `Domain/Shared/ValueObjects/{UserId, Money, Currency}` (Currency enum)
- `Application/Shared/Abstracts/{AbstractCommand, AbstractQuery, AbstractCommandHandler, AbstractQueryHandler, AbstractOutputDTO}`
- `Application/Shared/Contracts/{CommandInterface, QueryInterface, CommandBusInterface, QueryBusInterface, TransactionInterface}`
- `Application/Shared/Bus/{CommandBus, QueryBus}`
- `Infrastructure/Shared/Abstracts/{AbstractEloquentRepository, AbstractMapper}`
- `Infrastructure/Shared/Persistence/EloquentTransaction`
- `Presentation/Shared/Abstracts/AbstractApiController`
- `Presentation/Shared/Middleware/CorrelationIdMiddleware`

Her sınıf CODING_STANDARDS.md'deki signature'a HARFIYEN uymalı.

Test: `tests/Unit/Domain/Shared/ValueObjects/MoneyTest.php` — Money VO için temel test'ler (positive amount, currency match, add operation).

Commit: "feat(backend): shared kernel + base classes".

Bekle.

### TASK 0.6 — Database Migrations

DATABASE_SCHEMA.md'yi referans alarak migration'ları yaz. Sıralama:
1. `0000_setup/2024_01_01_000000_create_schemas.php` (CREATE SCHEMA)
2. `0000_setup/2024_01_01_000001_create_extensions.php` (uuid-ossp, pg_trgm)
3. `identity/2024_01_01_000100_create_users_table.php`
4. `identity/2024_01_01_000101_create_refresh_tokens_table.php`
5. `catalog/2024_01_01_000200_create_products_table.php`
6. `currency/2024_01_01_000300_create_exchange_rates_table.php`
7. `sales/2024_01_01_000400_create_carts_table.php`
8. `sales/2024_01_01_000401_create_cart_items_table.php`
9. `sales/2024_01_01_000402_create_orders_table.php` (sequence + table)
10. `sales/2024_01_01_000403_create_order_items_table.php`

`config/database.php`'de migrations path'ini şu şekilde set et:
```php
'migrations' => 'migrations',
```
Ve `app/Providers/AppServiceProvider.php`'de tüm modül migration path'lerini load et.

DATABASE_SCHEMA.md'deki ZORUNLU constraint'ler (CHECK, UNIQUE WHERE deleted_at IS NULL, partial indexes) raw SQL ile yazılmalı (Laravel Schema bunları desteklemiyor).

`docker-compose up -d postgres` çalıştır, `php artisan migrate` ile test et. Her tablonun yarattığını verify et.

Commit: "feat(backend): all migrations per DATABASE_SCHEMA.md".

Bekle.

### TASK 0.7 — Identity Module (İLK TAM MODÜL)

CODING_STANDARDS.md'deki pattern'leri TÜM detayıyla uygulayan ilk modül. SECURITY.md §4'teki token security gereklilikleri burada implement edilir.

Yaz:

**Domain layer:**
- ValueObjects: UserId (zaten Shared'de), RefreshTokenId, FamilyId, Email, HashedPassword
- Entities: User, RefreshToken
- Contracts: UserRepositoryInterface, RefreshTokenRepositoryInterface, PasswordHasherInterface, JwtIssuerInterface
- Exceptions: UserNotFoundException, InvalidCredentialsException, InvalidRefreshTokenException, RefreshTokenReplayException, AccountLockedException

**Application layer:**
- Commands: RegisterUserCommand, LoginUserCommand, RefreshTokenCommand, LogoutUserCommand
- Queries: GetCurrentUserQuery
- Handlers: tüm command/query handler'ları (RefreshTokenHandler'da SECURITY.md §4.4'teki replay detection logic'i HARFIYEN uygula)
- DTOs: UserDTO, TokenPairDTO

**Infrastructure layer:**
- Models: UserModel, RefreshTokenModel (Eloquent, schema-qualified table)
- Mappers: UserMapper, RefreshTokenMapper
- Repositories: EloquentUserRepository, EloquentRefreshTokenRepository (replay detection için family revoke method'u)
- Services: BcryptPasswordHasher, TymonJwtIssuer

**Presentation layer:**
- Controllers: AuthController (register, login, refresh, logout, me)
- Requests: RegisterRequest, LoginRequest
- Middleware: JwtAuthMiddleware, RoleMiddleware

**Provider:**
- BusServiceProvider'a Identity command/query mapping'lerini ekle
- RepositoryServiceProvider'a Identity binding'lerini ekle

**Tests:**
- Unit/Domain: User entity invariants, RefreshToken state machine
- Unit/Application: Her handler için happy path + error path (mocked repos)
- Feature: register, login, refresh (with replay detection), logout, me — SECURITY.md §10.1'deki tüm auth-related security test'leri

Commit'leri parçala: "feat(identity): domain layer", "feat(identity): application layer", "feat(identity): infrastructure", "feat(identity): http layer", "test(identity): unit + feature tests".

Bekle.

### TASK 0.8 — Security Middleware (Cross-Cutting)

SECURITY.md'deki middleware'leri yaz:
- `ProxyOriginMiddleware` (§2.2 Layer 2)
- `CsrfOriginMiddleware` (§4.5)
- (`JwtAuthMiddleware` zaten Identity'de yazıldı)
- (`RoleMiddleware` zaten Identity'de yazıldı)

`bootstrap/app.php`'de middleware alias'larını register et:
- `proxy.origin` → ProxyOriginMiddleware
- `csrf.origin` → CsrfOriginMiddleware
- `jwt.auth` → JwtAuthMiddleware
- `role` → RoleMiddleware
- `correlation.id` → CorrelationIdMiddleware

`config/security.php` dosyası yarat (`proxy_secret`, `allowed_origins`, vs.).

Rate limiter tanımları (`RouteServiceProvider::configureRateLimiting`) — SECURITY.md §7.3.

`routes/api.php`'de tüm route'lar `proxy.origin` + `csrf.origin` middleware grubunda.

**Tests:**
- Feature/Security/ProxyOriginTest: yanlış secret → 403, missing → 403, doğru → geçer
- Feature/Security/CsrfOriginTest: missing origin POST → 403
- Feature/Security/RateLimitTest: 6. login attempt → 429

Commit: "feat(security): proxy origin + csrf + rate limit middleware".

Bekle.

### TASK 0.9 — Logging Service (Backend Side)

LOGGING_SERVICE.md'deki Laravel kısımlarını implement et:
- `Domain/Logging/Contracts/AuditLoggerInterface`
- `Infrastructure/Logging/Audit/{AuditEvent, AuditLogger, AuditFallbackWriter}`
- `Infrastructure/Logging/Publishing/{RabbitMqPublisher, QueuedLogPublisher}`
- `Infrastructure/Logging/Publishing/Jobs/{PublishAppLogJob, PublishAccessLogJob}`
- `Infrastructure/Logging/Application/{RabbitMqLogChannel, RabbitMqLogHandler}`
- `Infrastructure/Logging/Access/AccessLogMiddleware`
- `App/Providers/LoggingServiceProvider`
- `App/Console/Commands/ReplayAuditFallbackCommand`

`config/logging.php`'ye `rabbitmq` channel ekle.
`config/logging-service.php` dosyası yarat.

Identity module'deki audit log call'larını (login, refresh_token_replay vs.) ekle.

**Test:** Feature test — login → audit log RabbitMQ'ya publish ediliyor mu (RabbitMQ mock veya container test).

Commit: "feat(logging): laravel-side audit + app + access loggers".

Bekle.

### TASK 0.10 — Catalog Module

Identity'deki pattern'leri tekrar uygulayarak Catalog modülü:
- Domain: Product entity (rename, changePrice, decreaseStock, increaseStock, activate/deactivate behavior'ları), ProductId, Sku, Stock VO
- Application: CreateProductCommand, UpdateProductCommand, DeleteProductCommand, AdjustStockCommand, GetProductQuery, ListProductsQuery + handler'ları
- Infrastructure: ProductModel, ProductMapper, EloquentProductRepository
- Presentation: ProductController + CreateProductRequest, UpdateProductRequest, ListProductsRequest

Audit logging: product create/update/delete event'leri.
Authorization: admin only (POST/PUT/DELETE), public read.

Tests: Product entity behavior'ları, CRUD handler'lar, IDOR test'leri (SECURITY.md §10.1), HTTP feature test'leri.

Commit'leri parçala (Identity gibi).

Bekle.

### TASK 0.11 — Sales Module (Cart + Order)

Sales modülü en kapsamlı modül. Burada Cart ve Order birlikte:
- Cart aggregate: AddItem, UpdateQuantity, RemoveItem, ClearCart command'ları
- Order aggregate: PlaceOrder (cart'ı order'a dönüştür), CancelOrder
- Cart-Order coupling: PlaceOrderHandler cart'ı `converted` yapar, items'ı snapshot'larla order_items'a kopyalar, currency conversion uygular (Currency module dependency)

Resource ownership (SECURITY.md §5.3): Order::assertCanBeViewedBy(UserId, Role) — admin tüm order'ları, customer sadece kendininkini.

Currency module dependency injection: OrderHandler currency converter kullanacak.

Tests:
- Cart: aynı ürün ekleme → quantity artar, başka kullanıcının cart'ı erişilemez
- Order: place success, place with insufficient stock → fail, cancel pending order → success, cancel completed order → fail
- IDOR: User A, B'nin order'ını GET → 404
- Concurrent place order (race condition test, optimistic lock veya transaction)

Commit'leri parçala.

Bekle.

### TASK 0.12 — Currency Module

- Domain: ExchangeRate entity, Currency VO (zaten var), CurrencyConverter domain service
- Application: SyncCurrencyRatesCommand (Frankfurter API'den çek), GetExchangeRateQuery, ListSupportedCurrenciesQuery
- Infrastructure: ExchangeRateModel, EloquentExchangeRateRepository, FrankfurterApiProvider (HTTP client), Redis cache wrapper
- Scheduled command: günlük rate sync (`app/Console/Kernel.php`)

Tests: Currency converter math, repository latest-rate query, Frankfurter mock test.

Commit.

Bekle.

### TASK 0.13 — Backend Dockerfile + Production Config

`backend/Dockerfile` (multi-stage: composer install + php-fpm).
`backend/docker/nginx.conf` veya php-fpm config.
`docker-compose.yml`'de backend service'i Dockerfile'a bağla.

`docker-compose up -d` ile tüm sistem ayağa kalksın. `php artisan migrate` çalışsın.
Health check endpoint'i (`GET /health`) çalışsın.

Commit: "feat(backend): production dockerfile + health check".

Bekle.

### TASK 0.14 — Frontend Bootstrap (Next.js 15)

```bash
cd frontend
pnpm create next-app@latest . --typescript --tailwind --app --src-dir --import-alias "@/*"
pnpm add zod
pnpm add @tanstack/react-query
pnpm add -D vitest @testing-library/react @testing-library/jest-dom
```

shadcn/ui setup:
```bash
pnpm dlx shadcn@latest init
pnpm dlx shadcn@latest add button card input form label
```

PROJECT_STRUCTURE.md'deki FSD klasör yapısını kur (app, pages-ui, widgets, features, entities, shared).

`.env.local` (gitignored):
```
LARAVEL_API_URL=http://backend:9000/api/v1
BACKEND_PROXY_SECRET=<docker-compose'daki ile aynı>
JWT_COOKIE_DOMAIN=localhost
NEXT_PUBLIC_APP_NAME=Dehasoft
```

ESLint kurallarına SECURITY.md §3.2 Rule 4'teki banned import'ları ekle.

Commit: "feat(frontend): nextjs skeleton + fsd structure".

Bekle.

### TASK 0.15 — Frontend Proxy Layer (BFF)

`frontend/src/app/api/[...path]/route.ts` — catch-all proxy (SECURITY.md §3.2 Rule 2 ve PROJECT_STRUCTURE.md proxy bölümü).
`frontend/src/app/api/auth/login/route.ts` — dedicated handler (cookie set eder)
`frontend/src/app/api/auth/refresh/route.ts` — dedicated handler
`frontend/src/app/api/auth/logout/route.ts` — dedicated handler
`frontend/src/shared/api/server-fetch.ts` — backend fetch wrapper (X-Proxy-Secret header)

Build sonrası leak detection script (`scripts/check-backend-leak.sh` — SECURITY.md §3.2 Rule 3).

Commit: "feat(frontend): proxy layer + auth route handlers + leak detection".

Bekle.

### TASK 0.16 — Frontend: Auth + Catalog Pages

Minimal sayfalar:
- `/login`, `/register` (auth pages)
- `/products` (catalog list)
- `/products/[id]` (product detail)
- `/cart` (cart page)
- `/checkout` (place order)
- `/orders` (user's orders)
- `/admin/products` (admin product CRUD — admin only)

shadcn/ui component'leri kullan. Server Components default, sadece interactivity gerektirenler 'use client'.

React Query ile data fetching. JWT cookie otomatik forward.

Commit'leri parçala.

Bekle.

### TASK 0.17 — Log Service (Node.js)

`log-service/` klasöründe LOGGING_SERVICE.md §8'deki yapıyı kur:
- TypeScript skeleton (tsconfig, package.json)
- Zod schemas (envelope, audit, app, access)
- 3 consumer (audit, app, access) — batch indexing
- ES bulk indexer + index resolver
- RabbitMQ connection + auto-reconnect
- Graceful shutdown
- Pino logger
- Tests: schema validation, indexResolver, sanitizer

Dockerfile + docker-compose entry.

Commit: "feat(log-service): nodejs consumer + bulk indexing".

Bekle.

### TASK 0.18 — Infrastructure Bootstrap

- `infrastructure/rabbitmq/definitions.json` — LOGGING_SERVICE.md §12.2'deki içerik
- `infrastructure/rabbitmq/rabbitmq.conf` — load_definitions ayarı
- `infrastructure/elasticsearch/templates/{audit,app,access}.json` — LOGGING_SERVICE.md §6.2
- `infrastructure/elasticsearch/ilm-policies/{audit,app,access}.json`
- `infrastructure/elasticsearch/bootstrap.sh` — index template + ILM bootstrap

`docker-compose up -d` sonrası bootstrap.sh otomatik çalışsın (init container veya bootstrap service).

End-to-end test: Laravel'den login → audit log RabbitMQ → Node consumer → Elasticsearch'te görünür mü? (Manuel curl ile ES query)

Commit: "feat(infrastructure): rabbitmq definitions + es templates + bootstrap".

Bekle.

### TASK 0.19 — CI Pipelines

`.github/workflows/`:
- `backend-ci.yml`: composer install, php artisan migrate, ./vendor/bin/pest, security audit (composer audit + gitleaks)
- `frontend-ci.yml`: pnpm install, pnpm build, leak detection script, pnpm test, eslint
- `log-service-ci.yml`: pnpm install, pnpm test, pnpm build

Tüm CI'lar PostgreSQL + Redis + RabbitMQ + Elasticsearch service container'larıyla çalışmalı (feature test'ler için).

Commit: "ci: github actions for all services".

Bekle.

### TASK 0.20 — README + Final Verification

Root `README.md` yaz:
- Proje açıklaması (Dehasoft test projesi, 10 günlük scope)
- Stack özeti
- Quick start: `docker-compose up -d` → `localhost:3000` erişimi
- Test komutları
- Doc map (5 doküman linki)
- Deployment notes (production'da backend port YOK, secret rotation, vs.)

Final verification checklist'i çalıştır:
- [ ] `docker-compose up -d` clean state'ten ayağa kalkıyor
- [ ] Frontend localhost:3000'de açılıyor
- [ ] Backend health check 200 dönüyor (sadece proxy üzerinden)
- [ ] Direct backend access 403 dönüyor (proxy secret olmadan)
- [ ] Register → login → product list → cart → place order → order list akışı çalışıyor
- [ ] Admin product create çalışıyor, customer rolü 403 alıyor
- [ ] Audit log Elasticsearch'te görünüyor (Kibana'da kontrol)
- [ ] Pest test suite full pass (`./vendor/bin/pest`)
- [ ] Frontend leak detection pass (no backend URL in bundle)
- [ ] `composer audit` clean
- [ ] `pnpm audit --prod` clean

Commit: "docs: comprehensive README + verification checklist".

## ETKİLEŞİM TARZI

- Her TASK'tan SONRA bana özet ver: ne yaptın, kaç dosya yarattın, hangi testler geçti.
- TASK içinde ARA SORU varsa önce sor, sonra devam.
- Eğer dokümanlarda BELİRSİZLİK varsa, varsayım yapma — bana sor.
- Eğer dokümanlardaki bir şey **mantıksız** geliyorsa, neden mantıksız bulduğunu açıkla, alternatif öner, ben karar vereyim.
- Eğer 10+ dakikadır debugging yapıyorsan, dur ve durumu açıkla — birlikte çözelim.

Şimdi TASK 0.1 ile başla: 5 dokümanı oku ve plan onayı için özetle.
````

---

## 🔧 KULLANIM NOTLARI

### 1️⃣ Token bütçesi

Bu prompt çok uzun bir iş — tek bir Claude Code oturumunda bitmez. Strateji:

- **TASK 0.1 - 0.6**: İlk oturum (skeleton + shared kernel + migrations)
- **TASK 0.7 - 0.9**: İkinci oturum (Identity + Security + Logging backend)
- **TASK 0.10 - 0.12**: Üçüncü oturum (Catalog + Sales + Currency)
- **TASK 0.13 - 0.16**: Dördüncü oturum (Backend Docker + Frontend)
- **TASK 0.17 - 0.20**: Beşinci oturum (Log service + Infrastructure + CI + README)

Her oturum başlangıcında: "Önceki konuşmada şu task'lara kadar gelmiştik. `git log` ile son commit'leri kontrol et, oradan devam et."

### 2️⃣ Stuck olduğunda

Claude Code bir noktada takılırsa ya da yanlış yola saparsa:
- `git diff` ile son değişiklikleri göster
- "Şu doküman ne diyordu? Tekrar oku ve karşılaştır" — Claude Code dokümanı yeniden bağlama yükler
- Çok kötüyse `git reset --hard HEAD` ile geri al, "task X'i yeniden başla" de

### 3️⃣ Sprint sonrası

Bu 0. sprint bitince elinde:
- ✅ Tam çalışan monorepo (3 servis + infrastructure)
- ✅ 4 modül CRUD'la dolu
- ✅ Tüm güvenlik katmanları
- ✅ Logging pipeline çalışıyor
- ✅ Test suite full
- ✅ CI pipeline yeşil

Yani aslında **Sprint 1 + Sprint 2'nin %80'i** halloluyor. Kalan iş polish + edge case + dokümantasyon.

### 4️⃣ Önemli uyarı: Claude Code'un sınırı

Claude Code **BU 5 DOKÜMAN'I OKUMAK ZORUNDA**. Eğer dokümanları okumadan kod yazmaya başlarsa:

> "Dur. Önce 5 dokümanı oku ve TASK 0.1'deki plan özetini ver. Bunu atlama."

de. Yoksa kendi varsayımlarına göre kod yazar, dokümanlardan sapar.

---

## 📦 DOSYA TESLİMİ

5 doküman + bu bootstrap promptu zip'lenebilir:

```
dehasoft-test-docs.zip
├── PROJECT_STRUCTURE.md
├── DATABASE_SCHEMA.md
├── CODING_STANDARDS.md
├── LOGGING_SERVICE.md
├── SECURITY.md
└── BOOTSTRAP_PROMPT.md   ← bu dosya
```