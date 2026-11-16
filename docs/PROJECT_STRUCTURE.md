# 🏛️ E-Ticaret Demo Projesi - Project Structure & Architecture Guide

> **CRITICAL:** Bu doküman projenin tüm dizin yapısını, katman sorumluluklarını ve klasör organizasyonunu tanımlar. **Tüm kod kesinlikle bu yapıya uymak zorundadır.** Implementation'a başlamadan önce bu dokümanın tamamını okuyun.

**Version:** 1.0
**Architecture:** Onion Architecture (Clean) + CQRS + Modular Monolith
**Backend Stack:** PHP 8.2+ (Strict Types), Laravel 11, PostgreSQL 15+, Redis 7+
**Frontend Stack:** Next.js 15 (App Router), TypeScript 5+, TailwindCSS, shadcn/ui
**Pattern:** Domain-Driven Design (Lite) + CQRS + Feature-Sliced Design (Frontend)

---

## 📋 TABLE OF CONTENTS

1. [System Overview](#1-system-overview)
2. [High-Level Architecture](#2-high-level-architecture)
3. [Module Breakdown](#3-module-breakdown)
4. [Backend Directory Structure](#4-backend-directory-structure)
5. [Backend Layer Responsibilities](#5-backend-layer-responsibilities)
6. [Backend Module Internal Structure](#6-backend-module-internal-structure)
7. [Shared Kernel](#7-shared-kernel)
8. [Frontend Directory Structure](#8-frontend-directory-structure)
9. [Frontend Layer Responsibilities](#9-frontend-layer-responsibilities)
10. [Frontend Feature Slice Anatomy](#10-frontend-feature-slice-anatomy)
11. [Proxy Layer (BFF) Structure](#11-proxy-layer-bff-structure)
12. [Database Schema Organization](#12-database-schema-organization)
13. [Naming Conventions](#13-naming-conventions)
14. [Dependency Rules](#14-dependency-rules)
15. [Repository & Branching](#15-repository--branching)
16. [Bootstrapping Checklist](#16-bootstrapping-checklist)

---

## 1. SYSTEM OVERVIEW

### 1.1 Project Scope

Basit bir e-ticaret platformu:

- **Auth:** JWT (access + refresh rotation), httpOnly cookie storage
- **Catalog:** Ürün CRUD, stok yönetimi
- **Sales:** Sepet işlemleri, sipariş oluşturma, sipariş durumu
- **Currency:** TRY/USD/EUR multi-currency, harici API entegrasyonu (Frankfurter)

### 1.2 Critical Architectural Rule

```
┌─────────────────────────────────────────────────────────┐
│  FRONTEND ASLA Laravel Backend'e DOĞRUDAN İSTEK ATMAZ.  │
│  Tüm istekler Next.js Proxy Layer (BFF) üzerinden gider. │
└─────────────────────────────────────────────────────────┘
```

- Browser → Next.js Proxy (`/api/*`) → Laravel Backend
- Laravel URL'i client bundle'a sızmaz
- Token'lar httpOnly cookie'de tutulur, JS'e expose edilmez
- Token refresh mantığı tamamen proxy'de gizlidir

### 1.3 Tech Stack Decisions

| Concern | Choice | Reasoning |
|---------|--------|-----------|
| Backend Framework | Laravel 11 | Ekosistem olgun, Eloquent + Queue + Cache + Schedule out-of-box |
| Architecture Pattern | Onion + CQRS | Iş mantığı framework'ten bağımsız, test edilebilir |
| Database | PostgreSQL 15 | JSONB, advanced indexing, schema separation |
| Cache | Redis 7 | Currency cache, refresh token revocation list |
| JWT Library | `tymon/jwt-auth` | Stateless access token, custom refresh table |
| Frontend Framework | Next.js 15 (App Router) | RSC + Route Handlers proxy'ye ideal |
| State Management | Zustand | Cart, currency, auth state - lightweight, persist desteği |
| Forms | React Hook Form + Zod | Type-safe validation |
| HTTP Client | Native `fetch` | RSC ile uyumlu, ekstra dependency yok |

---

## 2. HIGH-LEVEL ARCHITECTURE

### 2.1 System Diagram

```
┌──────────────────────────────────────────────────────────────────┐
│                          BROWSER (Client)                          │
│                  React Components, Zustand stores                 │
└────────────────────────────┬─────────────────────────────────────┘
                             │ HTTPS (only /api/*)
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│                    NEXT.JS APPLICATION (Edge)                     │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Pages / Server Components (UI)                            │  │
│  └────────────────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Middleware (Route Guards, Cookie Validation)             │  │
│  └────────────────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Route Handlers - PROXY LAYER (BFF)                       │  │
│  │  app/api/auth/login    app/api/auth/refresh              │  │
│  │  app/api/auth/logout   app/api/[...path] (catch-all)     │  │
│  └────────────────────────────────┬───────────────────────────┘  │
└────────────────────────────────────┼──────────────────────────────┘
                                     │ Internal Network (Bearer JWT)
                                     ▼
┌──────────────────────────────────────────────────────────────────┐
│                    LARAVEL API (Internal)                         │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Presentation Layer (Controllers, FormRequests)           │  │
│  └────────────────────────┬───────────────────────────────────┘  │
│                            ▼                                      │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Application Layer (Commands, Queries, Handlers, Bus)     │  │
│  └────────────────────────┬───────────────────────────────────┘  │
│                            ▼                                      │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Domain Layer (Entities, ValueObjects, Contracts)         │  │
│  └────────────────────────┬───────────────────────────────────┘  │
│                            ▼                                      │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Infrastructure Layer (Repos, Models, Mappers, Services)  │  │
│  └────┬──────────────────────┬────────────────────┬───────────┘  │
└───────┼──────────────────────┼────────────────────┼──────────────┘
        ▼                      ▼                    ▼
   PostgreSQL              Redis                Frankfurter API
   (4 schemas)         (cache/jobs)            (currency rates)
```

### 2.2 Request Flow Examples

**Example 1: User Login**
```
Client → POST /api/auth/login (Next Route Handler)
       → POST {LARAVEL_URL}/auth/login (server-side fetch)
       ← { access_token, refresh_token } (Laravel response)
       ← Set-Cookie: access_token=..., refresh_token=... (httpOnly)
       ← { user: {...} } (only safe data to client)
```

**Example 2: Authenticated Request**
```
Client → GET /api/products/123 (Next Route Handler catch-all)
       → reads access_token cookie
       → GET {LARAVEL_URL}/products/123 + Authorization: Bearer ...
       ← 200 OK (Laravel)
       ← 200 OK (forwarded to client)
```

**Example 3: Auto Refresh on 401**
```
Client → GET /api/orders (catch-all proxy)
       → GET {LARAVEL_URL}/orders + Bearer
       ← 401 Unauthorized
       → POST {LARAVEL_URL}/auth/refresh + refresh_token
       ← new tokens
       → retry original request with new access
       ← 200 OK
       ← Set-Cookie: rotated tokens
       ← 200 OK (transparent to client)
```

---

## 3. MODULE BREAKDOWN

Proje **4 ana modül + Shared Kernel** olarak organize edilmiştir. Her modül kendi bounded context'ine sahiptir.

### 3.1 Modules

| Module | Bounded Context | Responsibilities |
|--------|----------------|------------------|
| **Identity** | Authentication & User | User registration, login, logout, JWT issue, refresh token rotation, password hashing |
| **Catalog** | Product Management | Product CRUD, stock tracking, product listing/search |
| **Sales** | Cart & Order | Cart operations (add/update/remove), order creation, order status, order cancellation, stock reservation |
| **Currency** | Exchange Rates | Daily rate fetching from Frankfurter API, rate caching, currency conversion service |

### 3.2 Module Dependency Graph

```
┌──────────────┐
│   Identity   │ ◄──── (no module dependencies, only Shared)
└──────┬───────┘
       │ provides UserId
       ▼
┌──────────────┐         ┌──────────────┐
│   Catalog    │ ◄────── │    Sales     │
└──────┬───────┘         └──────┬───────┘
       │                        │
       │  uses ProductId        │  uses UserId, ProductId
       │  uses Money            │  uses Money, CurrencyService
       ▼                        ▼
┌──────────────────────────────────────┐
│            Currency                   │
│  (provides ConversionService)         │
└──────────────────────────────────────┘
```

**Rules:**
- ✅ Modules communicate only through **Application layer** (handlers can dispatch other commands/queries)
- ✅ Cross-module dependency only on **Shared ValueObjects** (UserId, Money) and **public contracts**
- ❌ No direct repository access across modules
- ❌ No direct entity access across modules

---

## 4. BACKEND DIRECTORY STRUCTURE

### 4.1 Root Layout

```
backend/
├── app/                           # Laravel framework code (minimal)
│   ├── Console/
│   │   └── Kernel.php             # Scheduler: currency sync
│   ├── Exceptions/
│   │   └── Handler.php            # Domain exception → HTTP mapping
│   ├── Http/
│   │   ├── Kernel.php             # Middleware groups
│   │   └── Middleware/
│   │       ├── JwtAuthMiddleware.php
│   │       ├── ProxyOriginGuard.php   # Reject non-proxy origins
│   │       └── CorrelationIdMiddleware.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── AuthServiceProvider.php
│       ├── BusServiceProvider.php          # ⭐ CQRS bus bindings
│       ├── RepositoryServiceProvider.php   # ⭐ Repo interface bindings
│       └── DomainServiceProvider.php       # Domain services bindings
│
├── src/                           # ⭐ All architecture code lives here
│   ├── Application/
│   ├── Domain/
│   ├── Infrastructure/
│   └── Presentation/
│
├── bootstrap/
├── config/
│   ├── auth.php
│   ├── jwt.php                    # tymon/jwt-auth config
│   ├── currency.php               # Frankfurter URL, supported codes
│   └── cors.php                   # Only Next.js origin allowed
│
├── database/
│   ├── factories/
│   ├── migrations/
│   │   ├── identity/              # Schema: identity
│   │   ├── catalog/               # Schema: catalog
│   │   ├── sales/                 # Schema: sales
│   │   └── currency/              # Schema: currency
│   └── seeders/
│       ├── IdentitySeeder.php
│       ├── CatalogSeeder.php
│       └── DatabaseSeeder.php
│
├── routes/
│   ├── api.php                    # Module route includes
│   ├── api/
│   │   ├── identity.php
│   │   ├── catalog.php
│   │   ├── sales.php
│   │   └── currency.php
│
├── resources/
│   └── lang/
│       ├── tr/
│       │   ├── identity.php
│       │   ├── catalog.php
│       │   ├── sales.php
│       │   └── currency.php
│       └── en/
│           └── ...
│
├── tests/
│   ├── Unit/
│   │   ├── Domain/                # Pure domain tests (no Laravel)
│   │   └── Application/           # Handler tests with mock repos
│   ├── Feature/
│   │   ├── Identity/              # HTTP-level integration tests
│   │   ├── Catalog/
│   │   └── Sales/
│   └── TestCase.php
│
├── composer.json                  # PSR-4 autoload for src/
├── docker-compose.yml
├── Dockerfile
├── .env.example
└── README.md
```

### 4.2 PSR-4 Autoload Configuration

`composer.json`:
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Application\\": "src/Application/",
            "Domain\\": "src/Domain/",
            "Infrastructure\\": "src/Infrastructure/",
            "Presentation\\": "src/Presentation/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    }
}
```

### 4.3 Layer Layout (Inside `src/`)

```
src/
├── Application/                   # Application Layer
│   ├── Identity/                  # Module
│   ├── Catalog/                   # Module
│   ├── Sales/                     # Module
│   ├── Currency/                  # Module
│   └── Shared/                    # Cross-module application code
│       ├── Abstracts/
│       │   ├── AbstractCommand.php
│       │   ├── AbstractQuery.php
│       │   ├── AbstractCommandHandler.php
│       │   ├── AbstractQueryHandler.php
│       │   └── AbstractOutputDTO.php
│       ├── Bus/
│       │   ├── CommandBus.php
│       │   └── QueryBus.php
│       └── Contracts/
│           ├── CommandInterface.php
│           ├── QueryInterface.php
│           ├── CommandHandlerInterface.php
│           ├── QueryHandlerInterface.php
│           ├── CommandBusInterface.php
│           ├── QueryBusInterface.php
│           └── TransactionInterface.php
│
├── Domain/                        # Domain Layer (pure PHP)
│   ├── Identity/
│   ├── Catalog/
│   ├── Sales/
│   ├── Currency/
│   └── Shared/
│       ├── Abstracts/
│       │   ├── AbstractEntity.php
│       │   ├── AbstractValueObject.php
│       │   ├── AbstractUuidValueObject.php
│       │   └── AbstractMapper.php
│       ├── Constants/
│       │   └── HttpStatus.php
│       ├── Contracts/
│       │   ├── EntityInterface.php
│       │   ├── ValueObjectInterface.php
│       │   └── RepositoryInterface.php
│       ├── Exceptions/
│       │   ├── DomainException.php
│       │   ├── NotFoundException.php
│       │   ├── ValidationException.php
│       │   ├── BusinessRuleException.php
│       │   ├── InfrastructureException.php
│       │   └── InvalidUuidException.php
│       └── ValueObjects/
│           ├── UserId.php             # Used by all modules
│           ├── Money.php              # amount + currency
│           └── Currency.php           # Enum: TRY, USD, EUR
│
├── Infrastructure/                # Infrastructure Layer
│   ├── Identity/
│   ├── Catalog/
│   ├── Sales/
│   ├── Currency/
│   └── Shared/
│       ├── Abstracts/
│       │   └── AbstractEloquentRepository.php
│       ├── Persistence/
│       │   ├── EloquentTransaction.php   # Implements TransactionInterface
│       │   └── BaseModel.php             # Common scopes, casts
│       └── External/
│           └── HttpClient.php            # Wrapper for Guzzle
│
└── Presentation/                  # Presentation Layer
    ├── Identity/
    ├── Catalog/
    ├── Sales/
    ├── Currency/
    └── Shared/
        ├── Abstracts/
        │   └── AbstractApiController.php
        ├── Concerns/
        │   ├── ResolvesRequestContext.php  # getUserId, getCorrelationId
        │   └── FormatsApiResponse.php      # success(), error()
        └── Responses/
            └── ApiResponse.php
```

---

## 5. BACKEND LAYER RESPONSIBILITIES

### 5.1 Domain Layer (`src/Domain/`)

**Purpose:** Pure business logic. Framework'e tamamen bağımsız.

**Contains:**
- **Entities:** Davranışlı business object'ler (Product, Order, Cart, User)
- **Value Objects:** Immutable kavramlar (ProductId, Money, OrderStatus)
- **Contracts:** Repository interface'leri (implementation YOK)
- **Exceptions:** Domain-specific exception'lar
- **Enums:** Domain enumeration'ları

**Strict Rules:**
- ❌ NO `use Illuminate\...`
- ❌ NO database access
- ❌ NO HTTP/Request kavramı
- ❌ NO `App\` namespace kullanımı
- ❌ NO Eloquent
- ✅ Sadece pure PHP + PSR-3 LoggerInterface (interface, implementation değil)
- ✅ Saatler `\DateTimeImmutable`, `\DateTimeInterface::ATOM`

### 5.2 Application Layer (`src/Application/`)

**Purpose:** Use case'leri orkestre eder. Domain'i koordine eder.

**Contains:**
- **Commands:** Write işlemleri (CreateProductCommand, AddToCartCommand)
- **Queries:** Read işlemleri (GetProductQuery, ListOrdersQuery)
- **Handlers:** Tüm business logic burada
- **DTOs:** API response için data transfer object'leri
- **Bus:** Command/Query bus implementasyonu

**Strict Rules:**
- ✅ Sadece Domain layer'a bağımlı olabilir
- ✅ Handler'lar TÜM business logic'i içerir
- ✅ DTO'lar `final readonly class`
- ✅ Command/Query'ler `final readonly class`
- ✅ Handler'lar `AbstractCommandHandler` veya `AbstractQueryHandler` extend eder
- ❌ Eloquent Model kullanımı YASAK (Repository üzerinden)

### 5.3 Infrastructure Layer (`src/Infrastructure/`)

**Purpose:** Teknik detaylar (DB, external API, queue, cache).

**Contains:**
- **Repositories:** Eloquent tabanlı repo implementasyonları
- **Models:** Eloquent modelleri (sadece bu katmanda)
- **Mappers:** Model ↔ Entity dönüşümü
- **External:** Frankfurter API client gibi dış servisler

**Strict Rules:**
- ✅ Domain contract'larını implement eder
- ✅ Repository'ler `AbstractEloquentRepository` extend eder
- ✅ Model'ler sadece bu katmanda yaşar
- ❌ Business logic YASAK

### 5.4 Presentation Layer (`src/Presentation/`)

**Purpose:** HTTP arayüzü, input/output handling.

**Contains:**
- **Controllers:** API endpoint'leri (HAFİF, sadece dispatch eder)
- **Requests:** FormRequest validation
- **Responses:** Response formatting

**Strict Rules:**
- ✅ Controller'lar **WEIGHTLESS** — iş mantığı YOK
- ✅ Sadece Command/Query oluştur, Bus'a dispatch et
- ✅ `AbstractApiController` extend eder
- ❌ Repository'ye doğrudan erişim YASAK
- ❌ Entity'ye doğrudan erişim YASAK

---

## 6. BACKEND MODULE INTERNAL STRUCTURE

Her modül 4 katmanda da kendi klasörüne sahiptir. Aşağıda **Catalog** modülü örnek olarak verilmiştir; diğer modüller de aynı şablonu izler.

### 6.1 Identity Module

```
src/Application/Identity/
├── Commands/
│   ├── RegisterUserCommand.php
│   ├── LoginUserCommand.php
│   ├── RefreshTokenCommand.php
│   └── LogoutUserCommand.php
├── Queries/
│   ├── GetCurrentUserQuery.php
│   └── ValidateRefreshTokenQuery.php
├── Handlers/
│   ├── Commands/
│   │   ├── RegisterUserHandler.php
│   │   ├── LoginUserHandler.php
│   │   ├── RefreshTokenHandler.php
│   │   └── LogoutUserHandler.php
│   └── Queries/
│       ├── GetCurrentUserHandler.php
│       └── ValidateRefreshTokenHandler.php
└── DTOs/
    ├── UserDTO.php
    ├── AuthTokenPairDTO.php
    └── TokenIssueResultDTO.php

src/Domain/Identity/
├── Entities/
│   ├── User.php
│   └── RefreshToken.php
├── ValueObjects/
│   ├── Email.php
│   ├── HashedPassword.php
│   ├── PlainPassword.php
│   └── RefreshTokenId.php
├── Contracts/
│   ├── UserRepositoryInterface.php
│   ├── RefreshTokenRepositoryInterface.php
│   ├── PasswordHasherInterface.php
│   └── JwtIssuerInterface.php
├── Exceptions/
│   ├── UserNotFoundException.php
│   ├── InvalidCredentialsException.php
│   ├── RefreshTokenRevokedException.php
│   ├── RefreshTokenExpiredException.php
│   └── EmailAlreadyExistsException.php
└── Enums/
    └── UserRole.php

src/Infrastructure/Identity/
├── Repositories/
│   ├── EloquentUserRepository.php
│   └── EloquentRefreshTokenRepository.php
├── Models/
│   ├── UserModel.php
│   └── RefreshTokenModel.php
├── Mappers/
│   ├── UserMapper.php
│   └── RefreshTokenMapper.php
└── Services/
    ├── BcryptPasswordHasher.php          # Implements PasswordHasherInterface
    └── TymonJwtIssuer.php                # Implements JwtIssuerInterface

src/Presentation/Identity/
├── Controllers/
│   ├── AuthController.php                # login, register, logout, refresh
│   └── UserController.php                # /me endpoint
└── Requests/
    ├── RegisterUserRequest.php
    ├── LoginRequest.php
    └── RefreshTokenRequest.php
```

### 6.2 Catalog Module

```
src/Application/Catalog/
├── Commands/
│   ├── CreateProductCommand.php
│   ├── UpdateProductCommand.php
│   ├── DeleteProductCommand.php
│   └── AdjustStockCommand.php
├── Queries/
│   ├── GetProductQuery.php
│   └── ListProductsQuery.php
├── Handlers/
│   ├── Commands/
│   │   ├── CreateProductHandler.php
│   │   ├── UpdateProductHandler.php
│   │   ├── DeleteProductHandler.php
│   │   └── AdjustStockHandler.php
│   └── Queries/
│       ├── GetProductHandler.php
│       └── ListProductsHandler.php
└── DTOs/
    ├── ProductDTO.php
    └── ProductListItemDTO.php

src/Domain/Catalog/
├── Entities/
│   └── Product.php
├── ValueObjects/
│   ├── ProductId.php
│   ├── ProductName.php
│   └── Stock.php
├── Contracts/
│   └── ProductRepositoryInterface.php
└── Exceptions/
    ├── ProductNotFoundException.php
    ├── InsufficientStockException.php
    └── InvalidPriceException.php

src/Infrastructure/Catalog/
├── Repositories/
│   └── EloquentProductRepository.php
├── Models/
│   └── ProductModel.php
└── Mappers/
    └── ProductMapper.php

src/Presentation/Catalog/
├── Controllers/
│   └── ProductController.php
└── Requests/
    ├── CreateProductRequest.php
    ├── UpdateProductRequest.php
    └── ListProductsRequest.php
```

### 6.3 Sales Module

```
src/Application/Sales/
├── Commands/
│   ├── AddItemToCartCommand.php
│   ├── UpdateCartItemQuantityCommand.php
│   ├── RemoveCartItemCommand.php
│   ├── ClearCartCommand.php
│   ├── PlaceOrderCommand.php
│   └── CancelOrderCommand.php
├── Queries/
│   ├── GetCartQuery.php
│   ├── GetOrderQuery.php
│   └── ListUserOrdersQuery.php
├── Handlers/
│   ├── Commands/
│   │   ├── AddItemToCartHandler.php
│   │   ├── UpdateCartItemQuantityHandler.php
│   │   ├── RemoveCartItemHandler.php
│   │   ├── ClearCartHandler.php
│   │   ├── PlaceOrderHandler.php
│   │   └── CancelOrderHandler.php
│   └── Queries/
│       ├── GetCartHandler.php
│       ├── GetOrderHandler.php
│       └── ListUserOrdersHandler.php
└── DTOs/
    ├── CartDTO.php
    ├── CartItemDTO.php
    ├── OrderDTO.php
    └── OrderItemDTO.php

src/Domain/Sales/
├── Entities/
│   ├── Cart.php
│   ├── CartItem.php
│   ├── Order.php
│   └── OrderItem.php
├── ValueObjects/
│   ├── CartId.php
│   ├── OrderId.php
│   ├── Quantity.php
│   └── OrderNumber.php
├── Enums/
│   └── OrderStatus.php                   # Pending, Completed, Cancelled
├── Contracts/
│   ├── CartRepositoryInterface.php
│   └── OrderRepositoryInterface.php
└── Exceptions/
    ├── CartNotFoundException.php
    ├── CartItemNotFoundException.php
    ├── EmptyCartException.php
    ├── OrderNotFoundException.php
    ├── OrderCannotBeCancelledException.php
    └── UnauthorizedOrderAccessException.php

src/Infrastructure/Sales/
├── Repositories/
│   ├── EloquentCartRepository.php
│   └── EloquentOrderRepository.php
├── Models/
│   ├── CartModel.php
│   ├── CartItemModel.php
│   ├── OrderModel.php
│   └── OrderItemModel.php
└── Mappers/
    ├── CartMapper.php
    └── OrderMapper.php

src/Presentation/Sales/
├── Controllers/
│   ├── CartController.php
│   └── OrderController.php
└── Requests/
    ├── AddCartItemRequest.php
    ├── UpdateCartItemRequest.php
    ├── PlaceOrderRequest.php
    └── ListOrdersRequest.php
```

### 6.4 Currency Module

```
src/Application/Currency/
├── Commands/
│   └── SyncCurrencyRatesCommand.php       # Triggered by scheduler
├── Queries/
│   ├── GetExchangeRateQuery.php
│   └── ListSupportedCurrenciesQuery.php
├── Handlers/
│   ├── Commands/
│   │   └── SyncCurrencyRatesHandler.php
│   └── Queries/
│       ├── GetExchangeRateHandler.php
│       └── ListSupportedCurrenciesHandler.php
└── DTOs/
    ├── ExchangeRateDTO.php
    └── ConvertedAmountDTO.php

src/Domain/Currency/
├── Entities/
│   └── ExchangeRate.php
├── ValueObjects/
│   └── ExchangeRateId.php
├── Services/
│   └── CurrencyConverter.php              # Domain service - pure logic
├── Contracts/
│   ├── ExchangeRateRepositoryInterface.php
│   └── ExchangeRateProviderInterface.php  # External API contract
└── Exceptions/
    ├── ExchangeRateNotFoundException.php
    └── UnsupportedCurrencyException.php

src/Infrastructure/Currency/
├── Repositories/
│   └── EloquentExchangeRateRepository.php
├── Models/
│   └── ExchangeRateModel.php
├── Mappers/
│   └── ExchangeRateMapper.php
├── Providers/
│   └── FrankfurterApiProvider.php         # Implements ExchangeRateProviderInterface
└── Console/
    └── SyncCurrencyRatesScheduler.php     # Bridges scheduler to command bus

src/Presentation/Currency/
├── Controllers/
│   └── CurrencyController.php             # GET /currencies, GET /currencies/{from}/{to}
└── Requests/
    └── ConvertCurrencyRequest.php
```

---

## 7. SHARED KERNEL

### 7.1 Cross-Module Value Objects

`src/Domain/Shared/ValueObjects/` içeriği:

| Value Object | Used By | Purpose |
|--------------|---------|---------|
| `UserId` | Identity, Sales | Tüm modüllerde kullanıcı kimliği |
| `Money` | Catalog, Sales, Currency | Tutar + para birimi (immutable) |
| `Currency` | Catalog, Sales, Currency | Enum: TRY, USD, EUR |

**Important:** Modüller arası kullanılan VO'lar `Domain/Shared` altında. Modül içine özel VO'lar (örn. `ProductId`, `OrderId`) ilgili modülün `ValueObjects/` klasöründe.

### 7.2 Shared Application Abstracts

```php
// src/Application/Shared/Abstracts/AbstractCommand.php
abstract readonly class AbstractCommand implements CommandInterface
{
    public function __construct(
        protected string $userId,
        protected string $correlationId,
        protected string $userIp,
    ) {}

    public function getUserId(): string { return $this->userId; }
    public function getCorrelationId(): string { return $this->correlationId; }
    public function getUserIp(): string { return $this->userIp; }

    abstract public function getCommandName(): string;
}
```

> **Not:** `OrganizationId` parametresi kaldırıldı çünkü tek-tenant projede gereksiz. UserId yeterli.

### 7.3 Bus Pattern

```
CommandBus (synchronous)
├── dispatch(CommandInterface): mixed
└── Internal: handler resolution via $commandHandlers map in BusServiceProvider

QueryBus (synchronous, no transaction)
├── ask(QueryInterface): mixed
└── Internal: handler resolution via $queryHandlers map
```

---

## 8. FRONTEND DIRECTORY STRUCTURE

Frontend, **Feature-Sliced Design (FSD)** prensiplerini Next.js App Router ile harmanlayan bir yapıda organize edilmiştir.

### 8.1 Root Layout

```
frontend/
├── src/
│   ├── app/                       # Next.js App Router (routes only)
│   ├── pages-ui/                  # Page-level composition
│   ├── widgets/                   # Composite UI blocks
│   ├── features/                  # User-facing capabilities
│   ├── entities/                  # Business entities (UI + domain)
│   └── shared/                    # Reusable, framework-agnostic
│
├── public/
│   └── images/
│
├── .env.example                   # Only server-side vars (BACKEND_INTERNAL_URL)
├── next.config.ts
├── tsconfig.json
├── tailwind.config.ts
├── postcss.config.js
├── components.json                # shadcn/ui config
├── package.json
├── Dockerfile
└── README.md
```

### 8.2 Layer Hierarchy (FSD)

```
┌──────────────────────────────────────────────────────┐
│  app/         Next.js routing & layout (THIN)        │
└────────────┬─────────────────────────────────────────┘
             ▼
┌──────────────────────────────────────────────────────┐
│  pages-ui/   Page composition (combines widgets)     │
└────────────┬─────────────────────────────────────────┘
             ▼
┌──────────────────────────────────────────────────────┐
│  widgets/    Independent composite UI blocks         │
│              (Header, ProductGrid, CartDrawer)       │
└────────────┬─────────────────────────────────────────┘
             ▼
┌──────────────────────────────────────────────────────┐
│  features/   User actions / capabilities             │
│              (login, add-to-cart, place-order)       │
└────────────┬─────────────────────────────────────────┘
             ▼
┌──────────────────────────────────────────────────────┐
│  entities/   Business entities (Product, Order, ...) │
└────────────┬─────────────────────────────────────────┘
             ▼
┌──────────────────────────────────────────────────────┐
│  shared/     UI kit, hooks, utils, types, api client │
└──────────────────────────────────────────────────────┘
```

**Dependency Rule:** Üst katman alt katmanı kullanabilir; tersi YASAK. Aynı katmanda yatay bağımlılık YASAK (örn. `features/login` → `features/place-order` direkt erişemez).

### 8.3 Detailed Tree

```
src/
├── app/                           # Next.js App Router
│   ├── (auth)/                    # Route group - public
│   │   ├── login/
│   │   │   └── page.tsx
│   │   └── register/
│   │       └── page.tsx
│   ├── (shop)/                    # Route group - authenticated
│   │   ├── layout.tsx
│   │   ├── products/
│   │   │   ├── page.tsx
│   │   │   └── [id]/
│   │   │       └── page.tsx
│   │   ├── cart/
│   │   │   └── page.tsx
│   │   ├── checkout/
│   │   │   └── page.tsx
│   │   └── orders/
│   │       ├── page.tsx
│   │       └── [id]/
│   │           └── page.tsx
│   ├── (admin)/
│   │   └── admin/
│   │       └── products/
│   │           ├── page.tsx
│   │           ├── new/page.tsx
│   │           └── [id]/edit/page.tsx
│   ├── api/                       # ⭐ Proxy Layer (BFF)
│   │   ├── auth/
│   │   │   ├── login/route.ts
│   │   │   ├── register/route.ts
│   │   │   ├── refresh/route.ts
│   │   │   ├── logout/route.ts
│   │   │   └── me/route.ts
│   │   └── [...path]/route.ts     # Catch-all generic proxy
│   ├── layout.tsx                 # Root layout
│   ├── globals.css
│   ├── not-found.tsx
│   └── error.tsx
│
├── pages-ui/                      # Page-level composition
│   ├── login/
│   │   ├── ui/LoginPage.tsx
│   │   └── index.ts
│   ├── product-list/
│   │   ├── ui/ProductListPage.tsx
│   │   └── index.ts
│   ├── product-detail/
│   ├── cart/
│   ├── checkout/
│   └── order-detail/
│
├── widgets/                       # Composite UI blocks
│   ├── header/
│   │   ├── ui/Header.tsx
│   │   ├── ui/CurrencySwitcher.tsx
│   │   ├── ui/UserMenu.tsx
│   │   └── index.ts
│   ├── product-grid/
│   │   ├── ui/ProductGrid.tsx
│   │   └── index.ts
│   ├── cart-drawer/
│   │   ├── ui/CartDrawer.tsx
│   │   └── index.ts
│   ├── order-summary/
│   └── footer/
│
├── features/                      # User-facing capabilities
│   ├── auth-login/
│   │   ├── api/loginMutation.ts
│   │   ├── model/useLoginForm.ts
│   │   ├── ui/LoginForm.tsx
│   │   └── index.ts
│   ├── auth-register/
│   ├── auth-logout/
│   ├── cart-add-item/
│   │   ├── api/addItemMutation.ts
│   │   ├── ui/AddToCartButton.tsx
│   │   └── index.ts
│   ├── cart-update-quantity/
│   ├── cart-remove-item/
│   ├── checkout-place-order/
│   ├── order-cancel/
│   ├── product-create/             # Admin
│   ├── product-update/             # Admin
│   ├── product-delete/             # Admin
│   └── currency-switch/
│
├── entities/                      # Business entities
│   ├── user/
│   │   ├── api/getCurrentUser.ts
│   │   ├── model/userStore.ts     # Zustand
│   │   ├── model/types.ts
│   │   ├── ui/UserBadge.tsx
│   │   └── index.ts
│   ├── product/
│   │   ├── api/listProducts.ts
│   │   ├── api/getProduct.ts
│   │   ├── model/types.ts
│   │   ├── ui/ProductCard.tsx
│   │   ├── ui/ProductPrice.tsx    # Reactive to currency
│   │   └── index.ts
│   ├── cart/
│   │   ├── api/getCart.ts
│   │   ├── model/cartStore.ts
│   │   ├── model/types.ts
│   │   ├── ui/CartItemRow.tsx
│   │   └── index.ts
│   ├── order/
│   │   ├── api/listOrders.ts
│   │   ├── api/getOrder.ts
│   │   ├── model/types.ts
│   │   ├── ui/OrderRow.tsx
│   │   ├── ui/OrderStatusBadge.tsx
│   │   └── index.ts
│   └── currency/
│       ├── api/listCurrencies.ts
│       ├── model/currencyStore.ts # Selected currency, persist
│       ├── model/types.ts
│       └── index.ts
│
└── shared/                        # Reusable, framework-agnostic
    ├── api/
    │   ├── client.ts              # Wraps fetch with sane defaults
    │   ├── errors.ts              # ApiError class
    │   └── types.ts               # ApiResponse<T>, ApiError shape
    ├── config/
    │   ├── env.ts                 # Server-only env access
    │   └── constants.ts
    ├── hooks/
    │   ├── useDebounce.ts
    │   └── useToast.ts
    ├── lib/
    │   ├── cn.ts                  # tailwind-merge
    │   ├── format.ts              # formatMoney, formatDate
    │   └── validators/            # Shared zod schemas
    ├── types/
    │   └── common.ts
    └── ui/                        # shadcn/ui + custom primitives
        ├── button.tsx
        ├── input.tsx
        ├── dialog.tsx
        ├── form.tsx
        ├── card.tsx
        ├── badge.tsx
        ├── select.tsx
        └── ...
```

---

## 9. FRONTEND LAYER RESPONSIBILITIES

### 9.1 `app/` Layer

**Purpose:** Next.js routing, layout composition, route handlers (proxy layer).

**Rules:**
- ✅ `page.tsx` files only **import and render** components from `pages-ui/`
- ✅ Page bodies are minimal (5-15 satır)
- ✅ Route handlers (`api/`) handle proxy logic only
- ❌ NO direct UI component code in `page.tsx` (use `pages-ui/`)

**Example:**
```tsx
// app/(shop)/products/page.tsx
import { ProductListPage } from '@/pages-ui/product-list';

export default function Page() {
  return <ProductListPage />;
}
```

### 9.2 `pages-ui/` Layer

**Purpose:** Page-level composition. Combines widgets and features.

**Rules:**
- ✅ Composes widgets, features, entities
- ✅ Handles page-level data fetching (Server Components)
- ❌ NO route definitions (those live in `app/`)

### 9.3 `widgets/` Layer

**Purpose:** Independent, reusable composite UI blocks.

**Rules:**
- ✅ Combines features + entities
- ✅ Self-contained (Header includes CurrencySwitcher + UserMenu)
- ❌ Cannot import other widgets

### 9.4 `features/` Layer

**Purpose:** User-facing capabilities (actions). Each feature = one user intent.

**Naming:** `{entity}-{action}` (e.g., `cart-add-item`, `auth-login`)

**Rules:**
- ✅ Contains action logic, mutation calls, action UI
- ✅ Each feature is self-contained (own api, model, ui)
- ❌ Cannot import other features

### 9.5 `entities/` Layer

**Purpose:** Business entities. UI representation + domain types + read APIs.

**Rules:**
- ✅ Defines entity types (Product, Order, User)
- ✅ Provides read APIs (listProducts, getProduct)
- ✅ Provides entity stores (Zustand)
- ✅ Provides entity UI primitives (ProductCard, OrderRow)
- ❌ NO mutation logic (that's a feature's job)
- ❌ Cannot import features or widgets

### 9.6 `shared/` Layer

**Purpose:** Reusable, framework-agnostic utilities.

**Rules:**
- ✅ UI kit (shadcn/ui), hooks, utils, API client wrapper
- ❌ NO business logic
- ❌ NO entity-specific code

---

## 10. FRONTEND FEATURE SLICE ANATOMY

Her slice (feature/entity/widget) standart bir iç yapıya sahiptir:

```
features/cart-add-item/
├── api/                           # Network calls, mutations
│   └── addItemMutation.ts
├── model/                         # State, hooks, business types
│   ├── useAddToCart.ts
│   └── types.ts
├── ui/                            # React components
│   └── AddToCartButton.tsx
├── lib/                           # Slice-internal utilities (optional)
└── index.ts                       # Public API (re-exports only)
```

**Public API Rule (Critical):**

Bir slice dışarıya yalnızca `index.ts` üzerinden export verir. İçeriden direkt path import YASAK.

✅ `import { AddToCartButton } from '@/features/cart-add-item';`
❌ `import { AddToCartButton } from '@/features/cart-add-item/ui/AddToCartButton';`

Bu kural ESLint `import/no-internal-modules` ile zorlanır.

---

## 11. PROXY LAYER (BFF) STRUCTURE

### 11.1 Route Handlers Layout

```
src/app/api/
├── auth/
│   ├── login/route.ts
│   │   └── POST: → Laravel /auth/login → set httpOnly cookies → return user
│   ├── register/route.ts
│   │   └── POST: → Laravel /auth/register → set cookies → return user
│   ├── refresh/route.ts
│   │   └── POST: → Laravel /auth/refresh (with refresh cookie) → rotate cookies
│   ├── logout/route.ts
│   │   └── POST: → Laravel /auth/logout → clear cookies
│   └── me/route.ts
│       └── GET: → Laravel /me → return user (uses access cookie)
│
└── [...path]/route.ts             # ⭐ Catch-all generic proxy
    ├── ALL methods (GET/POST/PUT/PATCH/DELETE)
    ├── Reads access cookie → Authorization: Bearer header
    ├── Forwards body, query, headers (filtered)
    ├── On 401 → tries refresh once → retries
    ├── On refresh failure → clears cookies → returns 401
    └── Streams response back to client
```

### 11.2 Server-Side Helpers

```
src/app/api/_lib/                  # Underscore prefix = Next ignores as route
├── tokens.ts                      # readAccessToken, readRefreshToken (cookies())
├── cookies.ts                     # setAuthCookies, clearAuthCookies, COOKIE_OPTS
├── backend.ts                     # backendFetch(path, init) - server-only
├── refresh.ts                     # tryRefresh(): boolean + sets new cookies
└── errors.ts                      # ProxyError, errorToResponse
```

### 11.3 Cookie Configuration

```ts
// src/app/api/_lib/cookies.ts
export const ACCESS_COOKIE = 'st_at';      // 15 min
export const REFRESH_COOKIE = 'st_rt';     // 7 days

export const COOKIE_BASE_OPTS = {
  httpOnly: true,
  secure: process.env.NODE_ENV === 'production',
  sameSite: 'strict' as const,
  path: '/',
};
```

### 11.4 Environment Variables

```
# .env.local (NOT committed)
BACKEND_INTERNAL_URL=http://api:8000        # Server-only, never exposed
JWT_COOKIE_SECRET=...                       # If we sign cookies
NODE_ENV=development
```

> **Critical:** `NEXT_PUBLIC_*` prefix ile **HİÇBİR backend bilgisi** expose edilmez. Tüm backend URL'leri server-side'da kalır.

---

## 12. DATABASE SCHEMA ORGANIZATION

PostgreSQL'in schema özelliği kullanılarak modüller veritabanı seviyesinde de izole edilir.

### 12.1 Schema List

```
postgres database: testproject
├── identity                       # Schema
│   ├── users
│   └── refresh_tokens
├── catalog                        # Schema
│   └── products
├── sales                          # Schema
│   ├── carts
│   ├── cart_items
│   ├── orders
│   └── order_items
└── currency                       # Schema
    └── exchange_rates
```

### 12.2 Migration Organization

`database/migrations/` altında modül bazlı klasörleme:

```
database/migrations/
├── identity/
│   ├── 2024_01_01_000001_create_users_table.php
│   └── 2024_01_01_000002_create_refresh_tokens_table.php
├── catalog/
│   └── 2024_01_01_000003_create_products_table.php
├── sales/
│   ├── 2024_01_01_000004_create_carts_table.php
│   ├── 2024_01_01_000005_create_cart_items_table.php
│   ├── 2024_01_01_000006_create_orders_table.php
│   └── 2024_01_01_000007_create_order_items_table.php
└── currency/
    └── 2024_01_01_000008_create_exchange_rates_table.php
```

Her migration kendi schema'sına ait tabloyu yaratır:
```php
Schema::connection('pgsql')->create('catalog.products', function ($table) {
    // ...
});
```

### 12.3 Naming Conventions (Database)

| Element | Convention | Example |
|---------|-----------|---------|
| Schema | `lowercase` | `identity`, `sales` |
| Table | `snake_case_plural` | `products`, `cart_items` |
| Column | `snake_case` | `created_at`, `user_id` |
| PK | `id` (UUID v4) | |
| FK | `{singular}_id` | `product_id`, `user_id` |
| Index | `idx_{table}_{cols}` | `idx_orders_user_id` |
| Unique | `uniq_{table}_{cols}` | `uniq_users_email` |

---

## 13. NAMING CONVENTIONS

### 13.1 Backend - Classes

| Type | Pattern | Example |
|------|---------|---------|
| Entity | `{Name}` | `Product`, `Order`, `User` |
| Value Object (UUID) | `{Name}Id` | `ProductId`, `OrderId` |
| Value Object (Complex) | `{Name}` | `Money`, `Email` |
| Enum | `{Name}` | `OrderStatus`, `Currency` |
| Command | `{Action}{Entity}Command` | `CreateProductCommand` |
| Query | `{Action}{Entity}Query` | `GetProductQuery`, `ListProductsQuery` |
| Handler | `{Action}{Entity}Handler` | `CreateProductHandler` |
| Repository Interface | `{Entity}RepositoryInterface` | `ProductRepositoryInterface` |
| Repository Impl | `Eloquent{Entity}Repository` | `EloquentProductRepository` |
| DTO | `{Entity}DTO` | `ProductDTO`, `OrderDTO` |
| Mapper | `{Entity}Mapper` | `ProductMapper` |
| Eloquent Model | `{Entity}Model` | `ProductModel` |
| Controller | `{Entity}Controller` | `ProductController` |
| FormRequest | `{Action}{Entity}Request` | `CreateProductRequest` |
| Exception | `{Entity}{Reason}Exception` | `ProductNotFoundException` |
| Domain Service | `{Purpose}` | `CurrencyConverter` |

### 13.2 Backend - Files

- One class per file
- Filename = ClassName.php
- Namespaces match directory structure

### 13.3 Frontend - Files

| Type | Convention | Example |
|------|-----------|---------|
| React Component | `PascalCase.tsx` | `ProductCard.tsx` |
| Hook | `use{Name}.ts` | `useDebounce.ts` |
| Store | `{name}Store.ts` | `cartStore.ts` |
| Type definitions | `types.ts` | |
| API call | `camelCase.ts` | `listProducts.ts` |
| Slice public API | `index.ts` | (re-exports only) |
| Page component | `{Name}Page.tsx` | `ProductListPage.tsx` |

### 13.4 Frontend - Types

```ts
// Entity types: PascalCase, no prefix
export type Product = { ... };
export type Order = { ... };

// Props: ComponentName + Props
export type ProductCardProps = { ... };

// Store types: lowercase, suffixed
export type CartState = { ... };
export type CartActions = { ... };
```

---

## 14. DEPENDENCY RULES

### 14.1 Backend Layer Dependencies

```
Domain          → (nothing, pure PHP)
Application     → Domain
Infrastructure  → Domain + Application
Presentation    → Application (NEVER Infrastructure or Domain directly)
```

**Enforcement:**

`composer.json`'a Deptrac veya benzeri bir araç entegre edilebilir. Manuel CI check'i:
- `src/Domain/` içinde `Illuminate\` araması → 0 olmalı
- `src/Application/` içinde `Infrastructure\` araması → 0 olmalı
- `src/Presentation/` içinde `Domain\.*\Entities\` araması → 0 olmalı

### 14.2 Frontend FSD Dependencies

```
app           → pages-ui, shared
pages-ui      → widgets, features, entities, shared
widgets       → features, entities, shared
features      → entities, shared
entities      → shared
shared        → (nothing internal)
```

**Enforcement:** ESLint plugin `eslint-plugin-boundaries` ile zorlanır.

### 14.3 Module Dependencies (Backend)

Modüller arası kurallar:
- ✅ Module A'nın Application Handler'ı, Module B'nin Application Command/Query'sini bus üzerinden çağırabilir
- ✅ `Domain/Shared/` içeriği tüm modüllerde kullanılabilir
- ❌ Module A'nın Handler'ı, Module B'nin Repository'sine veya Entity'sine doğrudan erişemez

**Example - Sales modülü Currency modülüne nasıl erişir:**

```php
// Sales/Handlers/Commands/PlaceOrderHandler.php
final class PlaceOrderHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepo,
        private readonly OrderRepositoryInterface $orderRepo,
        private readonly QueryBusInterface $queryBus,    // ⭐ Cross-module
        // ...
    ) { /* ... */ }

    protected function doHandle(CommandInterface $command): OrderDTO
    {
        // ...
        // Cross-module call via QueryBus, NOT direct repository
        $rate = $this->queryBus->ask(
            new GetExchangeRateQuery(from: 'TRY', to: $command->currency, ...)
        );
        // ...
    }
}
```

---

## 15. REPOSITORY & BRANCHING

### 15.1 Repository Layout

Tek monorepo, 3 servis + infrastructure:

```
testproject/
├── backend/                       # Laravel API (PHP 8.2)
├── frontend/                      # Next.js 15 (TypeScript)
├── log-service/                   # Node.js log consumer (TypeScript)
│
├── infrastructure/                # External service configs
│   ├── elasticsearch/
│   │   ├── templates/             # Index templates JSON
│   │   ├── ilm-policies/          # ILM policy definitions
│   │   └── bootstrap.sh
│   └── rabbitmq/
│       ├── definitions.json       # Exchanges, queues, bindings
│       └── rabbitmq.conf
│
├── docker-compose.yml             # All services (postgres, redis, rabbitmq, elasticsearch, kibana, backend, frontend, log-service, queue-worker)
│
├── .github/
│   └── workflows/
│       ├── backend-ci.yml
│       ├── frontend-ci.yml
│       └── log-service-ci.yml
│
├── docs/
│   ├── PROJECT_STRUCTURE.md       # ⭐ This file - tüm yapı
│   ├── DATABASE_SCHEMA.md         # Tablo, kolon, index detayları
│   ├── CODING_STANDARDS.md        # Kod template'leri ve pattern'ler
│   ├── LOGGING_SERVICE.md         # Log mimarisi (RabbitMQ + ES)
│   ├── ARCHITECTURE.md            # ADR'ler, flow diagrams
│   ├── API.md                     # Endpoint reference
│   └── DEPLOYMENT.md
└── README.md
```

**Document Map:**

| File | Audience | Purpose |
|------|----------|---------|
| `PROJECT_STRUCTURE.md` | All devs | Klasör yapısı, modüller, FSD |
| `DATABASE_SCHEMA.md` | Backend devs | Tablo, kolon, ilişki, index |
| `CODING_STANDARDS.md` | Backend devs | Kod template'leri ve pattern'ler |
| `LOGGING_SERVICE.md` | Backend + Node.js devs | Log mimarisi, RabbitMQ topolojisi |

### 15.2 Branch Strategy

```
main                # Stable, deployable
├── develop         # Integration branch
│   ├── feat/setup-base-architecture
│   ├── feat/identity-module
│   ├── feat/catalog-module
│   ├── feat/sales-module
│   ├── feat/currency-module
│   ├── feat/proxy-layer
│   └── feat/frontend-shell
└── hotfix/*
```

**Commit convention:** Conventional Commits
- `feat(catalog): add product create handler`
- `fix(proxy): handle 401 retry race condition`
- `docs(architecture): clarify module dependency rules`
- `refactor(sales): extract OrderNumber value object`

---

## 16. BOOTSTRAPPING CHECKLIST

Bu doküman onaylandıktan sonra, base'i kurarken sırayla aşağıdakiler yapılmalı:

### 16.1 Backend Bootstrap

- [ ] Laravel 11 install: `composer create-project laravel/laravel backend`
- [ ] `composer.json` PSR-4 autoload section güncellendi (`Application/`, `Domain/`, `Infrastructure/`, `Presentation/`)
- [ ] `src/` dizini ve 4 ana katman alt klasörü oluşturuldu
- [ ] `Domain/Shared/Abstracts/` içine base class iskeletleri kondu
- [ ] `Application/Shared/Abstracts/` içine command/query/handler base'leri kondu
- [ ] `Application/Shared/Bus/` içine `CommandBus` ve `QueryBus` implementasyonu yazıldı
- [ ] `BusServiceProvider` ve `RepositoryServiceProvider` `app/Providers/` altında oluşturuldu
- [ ] `config/app.php` providers array'ine eklendi
- [ ] `tymon/jwt-auth` install + `php artisan jwt:secret`
- [ ] `config/jwt.php` access token TTL = 15, refresh table custom
- [ ] PostgreSQL connection + 4 schema setup migration
- [ ] CORS config: sadece Next.js origin
- [ ] `Domain/Shared/ValueObjects/UserId`, `Money`, `Currency` yazıldı
- [ ] `Domain/Shared/Constants/HttpStatus` yazıldı
- [ ] `Domain/Shared/Exceptions/` hiyerarşisi kuruldu
- [ ] `JwtAuthMiddleware`, `ProxyOriginGuard`, `CorrelationIdMiddleware` yazıldı
- [ ] `routes/api.php` modül route includes ile çalışır durumda
- [ ] `tests/TestCase.php` ve unit/feature test klasör yapısı kuruldu

### 16.2 Frontend Bootstrap

- [ ] Next.js 15 App Router install: `npx create-next-app@latest frontend --typescript --tailwind --app --src-dir`
- [ ] `tsconfig.json` paths: `@/*` → `src/*`
- [ ] FSD klasör yapısı oluşturuldu (`app`, `pages-ui`, `widgets`, `features`, `entities`, `shared`)
- [ ] shadcn/ui init: `npx shadcn@latest init`
- [ ] Temel UI bileşenleri eklendi: button, input, dialog, form, card, badge, select
- [ ] Zustand install
- [ ] React Hook Form + Zod install
- [ ] `shared/api/client.ts` fetch wrapper yazıldı
- [ ] `shared/lib/cn.ts`, `format.ts` yazıldı
- [ ] `app/api/_lib/` proxy helper'ları (cookies, backend, refresh) yazıldı
- [ ] `app/api/auth/` 5 endpoint Route Handler iskeletleri kondu
- [ ] `app/api/[...path]/route.ts` catch-all proxy iskelet kondu
- [ ] `middleware.ts` route guard yazıldı
- [ ] ESLint `eslint-plugin-boundaries` config eklendi (FSD enforcement)
- [ ] Root layout + globals.css + theme tokens

### 16.3 Infrastructure Bootstrap

- [ ] `docker-compose.yml`: postgres + redis + backend (php-fpm + nginx) + frontend (node)
- [ ] `backend/Dockerfile` multi-stage
- [ ] `frontend/Dockerfile` multi-stage
- [ ] Internal network: backend sadece frontend'den erişilebilir, dış dünyaya kapalı
- [ ] `.env.example` her iki servis için paylaşıldı
- [ ] `make` targets: `up`, `down`, `migrate`, `seed`, `test`, `lint`

### 16.4 Smoke Test

Bootstrap sonu kontrol:
- [ ] `docker compose up` temiz makinada hatasız ayağa kalkıyor
- [ ] Backend `/health` endpoint 200 dönüyor
- [ ] Frontend `localhost:3000` açılıyor
- [ ] Browser DevTools Network → Backend URL **hiçbir yerde görünmüyor**
- [ ] Backend container'a dışarıdan port 8000 ile bağlanılamıyor (sadece frontend network'ten)
- [ ] `composer test` (backend), `pnpm test` (frontend) yeşil
- [ ] Lint yeşil (PHP_CodeSniffer + ESLint)

---

## 🚫 ABSOLUTE FORBIDDEN LIST

**Backend:**
- ❌ `Domain/` katmanında `Illuminate\` import etmek
- ❌ Controller'a iş mantığı koymak
- ❌ Handler'da Eloquent Model'e doğrudan erişmek (Repository üzerinden)
- ❌ ID'ler için string kullanmak (Value Object zorunlu)
- ❌ HTTP status code'unu hardcode etmek (`HttpStatus` constants)
- ❌ Hata mesajlarını hardcode etmek (i18n zorunlu)
- ❌ Modüller arası direkt repository erişimi
- ❌ Handler'dan Entity döndürmek (DTO zorunlu)
- ❌ `app/Models/` altında Eloquent model bulundurmak (`Infrastructure/{Module}/Models/` zorunlu)

**Frontend:**
- ❌ `app/page.tsx` içinde inline UI yazmak (`pages-ui/` zorunlu)
- ❌ Slice içeriklerine direkt path import (`index.ts` zorunlu)
- ❌ `widgets/` import zinciri (widget → widget YASAK)
- ❌ `features/` arası import (feature → feature YASAK)
- ❌ `entities/` katmanından mutation (sadece okuma)
- ❌ `NEXT_PUBLIC_BACKEND_URL` gibi backend bilgisi expose etmek
- ❌ Browser kodundan `fetch('http://laravel:8000/...')` yapmak
- ❌ Token'ları `localStorage`'a yazmak
- ❌ `shared/` katmanına entity-spesifik kod koymak

---

## ✅ DEFINITION OF DONE (For Base Setup)

Base structure tamam sayılır eğer:

1. Tüm dizin yapısı bu dokümandaki ile **birebir** uyuşuyorsa
2. PSR-4 autoload backend için `composer dump-autoload` hatasız geçiyorsa
3. Frontend ESLint boundaries kuralları yeşilse
4. `docker compose up` ile tüm servisler ayaklanıyorsa
5. Frontend → Proxy → Backend smoke test (örn. `/api/health`) çalışıyorsa
6. Browser DevTools'ta Laravel URL'i hiçbir yerde görünmüyorsa
7. Bu doküman repository root'unda `docs/PROJECT_STRUCTURE.md` olarak commit'lenmişse

---

**Last Updated:** 2026-05-04
**Version:** 1.0
**Author:** Architecture & PM (Ahmet Bekem)
**Project:** E-Ticaret Demo Projesi (10 gün)
