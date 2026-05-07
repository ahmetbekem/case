# 📘 Dehasoft Test Projesi - API Documentation Strategy

> **CRITICAL:** Dehasoft TESLİMAT kriterlerinden biri "API dokümantasyonu (Postman/Swagger)". Bu doküman entegrasyon stratejisini ve standartlarını tanımlar.

**Version:** 1.0
**Stack:** L5-Swagger (OpenAPI 3.0) + Postman Collection export
**Convention:** OpenAPI annotations on Controllers + auto-generation

---

## 📋 TABLE OF CONTENTS

1. [Strategy Overview](#1-strategy-overview)
2. [Library Choice: L5-Swagger](#2-library-choice-l5-swagger)
3. [Installation & Setup](#3-installation--setup)
4. [Annotation Standards](#4-annotation-standards)
5. [Schema Definitions (DTOs)](#5-schema-definitions-dtos)
6. [Authentication in Swagger](#6-authentication-in-swagger)
7. [Error Response Standards](#7-error-response-standards)
8. [Module-by-Module Endpoints](#8-module-by-module-endpoints)
9. [Postman Collection Export](#9-postman-collection-export)
10. [Frontend Proxy Documentation](#10-frontend-proxy-documentation)
11. [Documentation Deployment](#11-documentation-deployment)
12. [Checklist](#12-checklist)

---

## 1. STRATEGY OVERVIEW

### 1.1 Hedef

Dehasoft'a teslimde aşağıdakiler hazır olacak:

1. ✅ **Swagger UI:** Erişilebilir interaktif dokümantasyon (`/api/documentation`)
2. ✅ **OpenAPI JSON:** Standart export (`/docs/api-docs.json`)
3. ✅ **Postman Collection:** Hazır import edilebilir `.json` dosyası (`docs/postman/dehasoft.postman_collection.json`)
4. ✅ **Try-it-out:** Swagger UI'dan direkt test edilebilir (auth dahil)
5. ✅ **README'de link:** Hızlı erişim için

### 1.2 İki Katmanlı Dokümantasyon

**Önemli:** Mimaride 2 ayrı API var:

| Katman | Audience | Dokümantasyon |
|--------|----------|---------------|
| **Laravel API** (port 9000, internal) | Backend developers, Dehasoft inceleyici | Swagger/OpenAPI ile detaylı |
| **Next.js BFF** (`/api/*`, public) | Frontend developers | Markdown (kısa açıklama) |

Dehasoft'un göreceği asıl dokümantasyon **Laravel API** Swagger'ıdır. Next.js BFF aynı endpoint'leri proxy'ler, ek bir dokümantasyon yükü oluşturmaz.

### 1.3 Approach: Code-First Annotations

Annotation'lar Controller'lara yazılır → komut çalıştırılır → OpenAPI JSON üretilir → Swagger UI render eder.

```
Controller annotations (PHP attributes)
         ↓
   php artisan l5-swagger:generate
         ↓
   storage/api-docs/api-docs.json
         ↓
   Swagger UI ( /api/documentation )
```

**Avantajı:** Kod ile dokümantasyon aynı yerde, drift olmaz.

---

## 2. LIBRARY CHOICE: L5-SWAGGER

**Seçim:** `darkaonline/l5-swagger` paketini kullan.

**Neden:**
- ✅ Laravel'a en uygun, aktif maintenance
- ✅ OpenAPI 3.0 destekli
- ✅ PHP 8 attributes (annotation alternatifi) destekler
- ✅ Built-in Swagger UI server
- ✅ JSON/YAML export
- ✅ Postman'a import edilebilir output

**Alternatifler ve neden reddedildi:**
- `zircote/swagger-php` (raw): L5-Swagger zaten bunu kullanıyor, manuel kurulum tekrarı olur
- `vyuldashev/laravel-openapi`: Maintenance az
- API Platform: Laravel-first değil

---

## 3. INSTALLATION & SETUP

### 3.1 Composer

```bash
cd backend
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

### 3.2 Configuration

`config/l5-swagger.php` dosyası publish edilir. Önemli ayarlar:

```php
return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'Dehasoft API Documentation',
            ],
            'routes' => [
                'api' => 'api/documentation',  // Swagger UI URL
            ],
            'paths' => [
                'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
                'annotations' => [
                    base_path('src/Presentation'),
                    base_path('src/Application'),  // DTO'lar için
                ],
            ],
        ],
    ],
    'defaults' => [
        'routes' => [
            'docs' => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],
            'group_options' => [],
        ],
        'paths' => [
            'docs' => storage_path('api-docs'),
            'views' => base_path('resources/views/vendor/l5-swagger'),
            'base' => env('L5_SWAGGER_BASE_PATH', null),
            'excludes' => [],
        ],
        'scanOptions' => [
            'analyser' => null,
            'analysis' => null,
            'processors' => [],
            'pattern' => null,
            'exclude' => [],
            'open_api_spec_version' => env('L5_SWAGGER_OPEN_API_SPEC_VERSION', '3.0.0'),
        ],
        'securityDefinitions' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
                'cookieAuth' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => 'access_token',
                ],
            ],
            'security' => [
                ['cookieAuth' => []],
            ],
        ],
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
        'proxy' => false,
        'additional_config_url' => null,
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
        'validator_url' => null,
        'ui' => [
            'display' => [
                'dark_mode' => true,
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'list'),
                'filter' => env('L5_SWAGGER_UI_FILTERS', true),
            ],
            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', true),
            ],
        ],
    ],
];
```

### 3.3 .env

```bash
L5_SWAGGER_GENERATE_ALWAYS=true       # Development: her request'te regen
L5_SWAGGER_USE_ABSOLUTE_PATH=true
L5_SWAGGER_UI_PERSIST_AUTHORIZATION=true
L5_SWAGGER_OPEN_API_SPEC_VERSION=3.0.0
```

### 3.4 OpenAPI Root Definition

Bir base controller veya dedicated dosyada API'nin root info'sunu tanımla:

```php
<?php

declare(strict_types=1);

namespace Presentation\Shared\Documentation;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Dehasoft Test Projesi - E-ticaret API. Tüm endpoint\'ler Next.js BFF üzerinden çağrılmalıdır. Direct backend access yasaktır (X-Proxy-Secret zorunlu).',
    title: 'Dehasoft API',
    contact: new OA\Contact(email: 'ahmet@example.com'),
)]
#[OA\Server(url: 'http://localhost:9000/api/v1', description: 'Local development')]
#[OA\Server(url: 'https://api.example.com/api/v1', description: 'Production')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\SecurityScheme(
    securityScheme: 'cookieAuth',
    type: 'apiKey',
    in: 'cookie',
    name: 'access_token',
)]
#[OA\Tag(name: 'Authentication', description: 'Login, register, token operations')]
#[OA\Tag(name: 'Catalog', description: 'Product CRUD operations')]
#[OA\Tag(name: 'Catalog (Admin)', description: 'Admin-only product management')]
#[OA\Tag(name: 'Cart', description: 'Shopping cart operations')]
#[OA\Tag(name: 'Orders', description: 'Order placement and tracking')]
#[OA\Tag(name: 'Orders (Admin)', description: 'Admin order management')]
#[OA\Tag(name: 'Currency', description: 'Exchange rates')]
#[OA\Tag(name: 'Users (Admin)', description: 'Admin user management')]
final class ApiDocumentation
{
    // Empty class — sadece annotation'lar için.
}
```

### 3.5 Generate Command

```bash
php artisan l5-swagger:generate
```

Output: `storage/api-docs/api-docs.json`

**Erişim:**
- Swagger UI: `http://localhost:9000/api/documentation`
- JSON: `http://localhost:9000/docs/api-docs.json`

### 3.6 Production Considerations

```bash
# .env.production
L5_SWAGGER_GENERATE_ALWAYS=false  # Production'da deploy zamanı bir kez generate
```

Production'da swagger UI'a erişim:
- **Opsiyon A:** Public (Dehasoft için ideal — değerlendirici erişebilir)
- **Opsiyon B:** Basic auth ile korumalı (uzun vadeli production için)

Test için Opsiyon A öneriliyor.

---

## 4. ANNOTATION STANDARDS

### 4.1 PHP 8 Attributes Kullan (Annotation YOK)

**Eski (annotation, KULLANMA):**
```php
/**
 * @OA\Get(...)
 */
public function index() {}
```

**Yeni (PHP 8 attribute, KULLAN):**
```php
#[OA\Get(...)]
public function index(): JsonResponse {}
```

### 4.2 Controller Method Pattern

Her endpoint **tüm bu alanları** içermeli:

```php
<?php

declare(strict_types=1);

namespace Presentation\Catalog\Controllers;

use OpenApi\Attributes as OA;

final class ProductController extends AbstractApiController
{
    #[OA\Get(
        path: '/catalog/products',
        summary: 'Ürün listesi',
        description: 'Aktif ürünlerin sayfalanmış listesini döner. Public endpoint (auth gerekmez).',
        tags: ['Catalog'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1, minimum: 1),
            ),
            new OA\Parameter(
                name: 'pageSize',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100),
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Ürün adında arama',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'displayCurrency',
                in: 'query',
                required: false,
                description: 'Fiyatları bu para biriminde göster',
                schema: new OA\Schema(type: 'string', enum: ['TRY', 'USD', 'EUR'], default: 'TRY'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Başarılı',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProductDTO'),
                        ),
                        new OA\Property(
                            property: 'meta',
                            ref: '#/components/schemas/PaginationMeta',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
            ),
        ],
    )]
    public function index(ListProductsRequest $request): JsonResponse
    {
        // ...
    }

    #[OA\Post(
        path: '/catalog/products',
        summary: 'Yeni ürün oluştur',
        description: 'Sadece admin rolü erişebilir.',
        security: [['cookieAuth' => []]],
        tags: ['Catalog (Admin)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateProductRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Ürün oluşturuldu',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/ProductDTO'),
                        new OA\Property(property: 'message', type: 'string'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')),
            new OA\Response(response: 409, description: 'Duplicate SKU', content: new OA\JsonContent(ref: '#/components/schemas/ConflictError')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(CreateProductRequest $request): JsonResponse
    {
        // ...
    }
}
```

### 4.3 Annotation Checklist Per Endpoint

Her endpoint annotation'ında **OLMAZSA OLMAZ** alanlar:

- [ ] `path` — `/catalog/products` formatında (prefix yok)
- [ ] `summary` — Kısa, Türkçe (1 cümle)
- [ ] `description` — Detaylı, Türkçe (auth, side effect, edge case'ler)
- [ ] `tags` — Modül kategorisi
- [ ] `security` — Auth gerekiyorsa `[['cookieAuth' => []]]`
- [ ] `parameters` — Query/path/header parametreleri
- [ ] `requestBody` — POST/PUT/PATCH için
- [ ] `responses` — En az 200/201, 4xx hata kod'ları, 5xx yoksa atla

---

## 5. SCHEMA DEFINITIONS (DTOs)

### 5.1 DTO'lara Schema Annotation

Her DTO'ya `#[OA\Schema]` ekle:

```php
<?php

declare(strict_types=1);

namespace Application\Catalog\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Catalog\Entities\Product;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductDTO',
    title: 'Product DTO',
    description: 'Ürün bilgisi',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'sku', type: 'string', example: 'BABT-SYAH-42'),
        new OA\Property(property: 'name', type: 'string', example: 'Babet Ayakkabı Siyah 42'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'basePrice', type: 'number', format: 'float', example: 1000.00),
        new OA\Property(property: 'baseCurrency', type: 'string', enum: ['TRY', 'USD', 'EUR'], example: 'TRY'),
        new OA\Property(property: 'displayPrice', type: 'number', format: 'float', example: 28.50),
        new OA\Property(property: 'displayCurrency', type: 'string', enum: ['TRY', 'USD', 'EUR'], example: 'USD'),
        new OA\Property(property: 'exchangeRate', type: 'number', format: 'float', example: 0.0285),
        new OA\Property(property: 'stock', type: 'integer', example: 50),
        new OA\Property(property: 'isActive', type: 'boolean', example: true),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final readonly class ProductDTO extends AbstractOutputDTO
{
    // ... existing code
}
```

### 5.2 FormRequest Schema

Request body'leri ayrı schema olarak tanımla — re-use için:

```php
#[OA\Schema(
    schema: 'CreateProductRequest',
    required: ['sku', 'name', 'price', 'stock'],
    properties: [
        new OA\Property(property: 'sku', type: 'string', minLength: 3, maxLength: 64, pattern: '^[A-Z0-9\-]+$', example: 'BABT-SYAH-42'),
        new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 255, example: 'Babet Ayakkabı'),
        new OA\Property(property: 'description', type: 'string', maxLength: 5000, nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, maximum: 999999.99, example: 1000.00),
        new OA\Property(property: 'currency', type: 'string', enum: ['TRY', 'USD', 'EUR'], default: 'TRY'),
        new OA\Property(property: 'stock', type: 'integer', minimum: 0, maximum: 1000000, example: 50),
    ],
)]
final class CreateProductRequest extends FormRequest
{
    // ... existing code
}
```

### 5.3 Common Schemas

`src/Presentation/Shared/Documentation/CommonSchemas.php` — re-usable schemas:

```php
<?php

declare(strict_types=1);

namespace Presentation\Shared\Documentation;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'currentPage', type: 'integer', example: 1),
        new OA\Property(property: 'pageSize', type: 'integer', example: 20),
        new OA\Property(property: 'total', type: 'integer', example: 150),
        new OA\Property(property: 'totalPages', type: 'integer', example: 8),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
            example: ['email' => ['Email is required.']],
        ),
    ],
)]
#[OA\Schema(
    schema: 'UnauthorizedError',
    properties: [
        new OA\Property(property: 'error', type: 'object', properties: [
            new OA\Property(property: 'code', type: 'string', example: 'UNAUTHORIZED'),
            new OA\Property(property: 'message', type: 'string', example: 'Token gerekli veya geçersiz.'),
        ]),
    ],
)]
#[OA\Schema(
    schema: 'ForbiddenError',
    properties: [
        new OA\Property(property: 'error', type: 'object', properties: [
            new OA\Property(property: 'code', type: 'string', example: 'FORBIDDEN'),
            new OA\Property(property: 'message', type: 'string', example: 'Bu işlem için yetkiniz yok.'),
        ]),
    ],
)]
#[OA\Schema(
    schema: 'NotFoundError',
    properties: [
        new OA\Property(property: 'error', type: 'object', properties: [
            new OA\Property(property: 'code', type: 'string', example: 'NOT_FOUND'),
            new OA\Property(property: 'message', type: 'string'),
        ]),
    ],
)]
#[OA\Schema(
    schema: 'ConflictError',
    properties: [
        new OA\Property(property: 'error', type: 'object', properties: [
            new OA\Property(property: 'code', type: 'string', example: 'CONFLICT'),
            new OA\Property(property: 'message', type: 'string'),
        ]),
    ],
)]
#[OA\Schema(
    schema: 'RateLimitError',
    properties: [
        new OA\Property(property: 'error', type: 'object', properties: [
            new OA\Property(property: 'code', type: 'string', example: 'RATE_LIMIT_EXCEEDED'),
            new OA\Property(property: 'message', type: 'string'),
        ]),
    ],
)]
final class CommonSchemas
{
    // Empty class — sadece annotation'lar için.
}
```

---

## 6. AUTHENTICATION IN SWAGGER

### 6.1 Cookie-Based Auth (Default)

Mimaride JWT httpOnly cookie'de. Swagger UI'da "Try it out" yaparken:

```
1. POST /auth/login → Set-Cookie: access_token=...
2. Sonraki istekler otomatik cookie'yi kullanır
```

Swagger UI bunu otomatik yapmaz — kullanıcı manuel **Authorize** butonuna basıp `access_token` değerini girer.

**Alternatif (geliştirme için):** Bearer token desteği:

```php
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
```

Login endpoint'i hem cookie hem JWT response döner (development için):

```php
// Sadece development!
return response()->json([
    'data' => $tokens,
    'message' => 'Login successful',
])->cookie(...);  // Production'da sadece cookie
```

### 6.2 Auth Required Endpoint

```php
#[OA\Get(
    path: '/auth/me',
    summary: 'Mevcut kullanıcı bilgisi',
    security: [['cookieAuth' => []]],  // ⭐ Auth zorunlu
    tags: ['Authentication'],
    responses: [
        new OA\Response(response: 200, description: 'Başarılı', content: new OA\JsonContent(...)),
        new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')),
    ],
)]
public function me(): JsonResponse {}
```

### 6.3 Public Endpoint

```php
#[OA\Get(
    path: '/catalog/products',
    summary: 'Ürün listesi',
    // security YOK — public
    tags: ['Catalog'],
    responses: [...],
)]
public function index() {}
```

---

## 7. ERROR RESPONSE STANDARDS

### 7.1 Common Error Format

Tüm hatalar şu formatta:

```json
{
  "error": {
    "code": "MACHINE_READABLE_CODE",
    "message": "Kullanıcıya gösterilecek mesaj",
    "context": {
      "field": "additional debug info (opsiyonel)"
    }
  }
}
```

Validation errors farklı (Laravel default):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Email is required."]
  }
}
```

### 7.2 Standard HTTP Codes

Her endpoint dokümantasyonunda potansiyel hata kodları:

| HTTP | Schema | Ne Zaman |
|------|--------|----------|
| 200 | (Success DTO) | Başarılı GET, PUT, PATCH |
| 201 | (Success DTO) | Başarılı POST (resource yarattı) |
| 204 | (none) | Başarılı DELETE |
| 401 | UnauthorizedError | Auth gerekli ama yok/geçersiz |
| 403 | ForbiddenError | Auth var ama yetki yok |
| 404 | NotFoundError | Resource yok |
| 409 | ConflictError | Business rule violation (duplicate, insufficient stock) |
| 422 | ValidationError | Input validation fail |
| 429 | RateLimitError | Rate limit exceeded |

---

## 8. MODULE-BY-MODULE ENDPOINTS

Endpoint inventory — her birinin annotation'u olmalı. **Bu liste TASK 0.10-0.12'de implementation referansı.**

### 8.1 Identity (Authentication)

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| POST | `/auth/register` | Yeni kullanıcı kaydı | Public |
| POST | `/auth/login` | Giriş + token alma | Public |
| POST | `/auth/refresh` | Token yenileme | Cookie (refresh_token) |
| POST | `/auth/logout` | Çıkış + token revoke | Cookie |
| GET | `/auth/me` | Mevcut kullanıcı | Cookie |

### 8.2 Identity Admin

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/admin/identity/users` | Kullanıcı listesi | Admin |
| GET | `/admin/identity/users/{id}` | Kullanıcı detay | Admin |
| PATCH | `/admin/identity/users/{id}/activate` | Aktifleştir | Admin |
| PATCH | `/admin/identity/users/{id}/deactivate` | Pasifleştir | Admin |

### 8.2A Identity (Addresses)

ADR-018 — Adres rehberi. Tüm endpoint'ler `jwt.auth` zorunlu;
IDOR-protected: başka kullanıcının adres ID'sine erişim de 404
döner (anti-enumeration). Limit: aktif 10 adres / kullanıcı.
İlk eklenen adres otomatik default delivery + billing olur.

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/identity/addresses` | Kullanıcı adres listesi | User |
| POST | `/identity/addresses` | Yeni adres ekle | User |
| GET | `/identity/addresses/{id}` | Adres detay | User (own) |
| PUT | `/identity/addresses/{id}` | Adres güncelle | User (own) |
| DELETE | `/identity/addresses/{id}` | Adres sil (soft) | User (own) |
| PATCH | `/identity/addresses/{id}/default-delivery` | Varsayılan teslimat ayarla | User (own) |
| PATCH | `/identity/addresses/{id}/default-billing` | Varsayılan fatura ayarla | User (own) |

**Default re-pick policy:** Default işaretli bir adres silinince, en
son eklenen aktif adres otomatik default'a promote olur. Hiç
kalmadıysa default boş kalır (kullanıcı yeni eklemek zorunda).

**Snapshot pattern:** `PlaceOrder` adres seçimi ister
(`deliveryAddressId` zorunlu). Sipariş anında adres JSONB olarak
`orders.delivery_address` + `orders.billing_address` kolonlarına
snapshot'lanır — adres sonradan silinse bile sipariş bilgisi bozulmaz
(KVKK + audit).

### 8.2B Locations (Public)

ADR-018 — TR il/ilçe statik veri. Auth gerekmez (form dropdown'larından
çağrılır, login öncesi de erişilebilir). Veri kaynak: turkiyeapi.dev,
build sırasında transform; offline çalışır, runtime API bağımlılığı
yok.

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/locations/cities` | Türkiye il listesi (81 il) | Public |
| GET | `/locations/cities/{city}/districts` | İl ilçeleri (~973 ilçe toplam) | Public |

### 8.3 Catalog

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/catalog/products` | Ürün listesi (active) | Public |
| GET | `/catalog/products/{id}` | Ürün detay | Public |

### 8.4 Catalog Admin

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/admin/catalog/products` | Tüm ürünler (deleted dahil) | Admin |
| POST | `/admin/catalog/products` | Yeni ürün | Admin |
| PUT | `/admin/catalog/products/{id}` | Ürün güncelle | Admin |
| DELETE | `/admin/catalog/products/{id}` | Ürün sil (soft) | Admin |
| PATCH | `/admin/catalog/products/{id}/toggle-active` | Aktif/pasif | Admin |
| PATCH | `/admin/catalog/products/{id}/adjust-stock` | Stok değiştir | Admin |

### 8.5 Sales (Cart)

ADR-017 — Cart endpoint'leri **auth-optional**: middleware stack
`optional.jwt + guest.session`. Authenticated user için `user_id`
attribute set edilir; guest için `guest_session_id` cookie'den okunur
veya yeni UUID v4 üretilip `Set-Cookie: guest_cart_token=…` header'ı
ile dönülür (httpOnly + Secure + SameSite=Strict, 30 gün TTL). Backend
her zaman bir Cart döner — fresh user/guest için items=[].

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/sales/cart` | Aktif sepetimi getir | Optional (user OR guest cookie) |
| POST | `/sales/cart/items` | Sepete ürün ekle | Optional (user OR guest cookie) |
| PATCH | `/sales/cart/items/{id}` | Quantity güncelle | Optional (user OR guest cookie) |
| DELETE | `/sales/cart/items/{id}` | Sepetten çıkar | Optional (user OR guest cookie) |
| DELETE | `/sales/cart` | Sepeti boşalt | Optional (user OR guest cookie) |

**Login/Register merge:** İstemci login/register isteğinde
`guest_cart_token` cookie de gönderirse backend `MergeGuestCartCommand`
tetikler (3 senaryo: no guest cart no-op; user cart yok →
transferOwnership; ikisi de var → CartMerger.applyToUserCart, oldest
snapshot price korunur, guest cart abandoned'a alınır). Response'ta
`data.cartMerged: bool` döner; success akışında BFF cookie'yi expire
eder.

**Cleanup job:** `php artisan sales:clean-guest-carts` günlük 03:00
(routes/console.php). Yalnızca status=active + expires_at<=now() guest
cart'lar silinir; cart_items FK ON DELETE CASCADE.

### 8.6 Sales (Orders)

Cart'tan farklı olarak Orders endpoint'leri **auth zorunlu** (KVKK
denetim — guest order yok). ADR-018 — `POST /sales/orders` istek
body'sinde `deliveryAddressId` (zorunlu) + `billingAddressId`
(opsiyonel, null → delivery ile aynı). Backend adres ownership
doğrular (IDOR-safe; başka kullanıcının adresi 404), sipariş anında
JSONB snapshot'a alır. OrderDTO `deliveryAddress` + `billingAddress`
alanlarını surface eder; orijinal adres silinse bile bu snapshot
korunur.

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/sales/orders` | Siparişlerim | Customer |
| GET | `/sales/orders/{id}` | Sipariş detay | Customer (own) |
| POST | `/sales/orders` | Sepetten sipariş oluştur | Customer |
| POST | `/sales/orders/{id}/cancel` | Pending sipariş iptal | Customer (own) |

### 8.7 Sales Admin

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/admin/sales/orders` | Tüm siparişler | Admin |
| GET | `/admin/sales/orders/{id}` | Sipariş detay (any) | Admin |
| PATCH | `/admin/sales/orders/{id}/complete` | Pending → Completed | Admin |
| PATCH | `/admin/sales/orders/{id}/cancel` | Admin iptal (reason zorunlu) | Admin |
| GET | `/admin/sales/stats` | Dashboard metrikleri | Admin |

### 8.8 Currency

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/currency/rates` | Aktif kurlar | Public |
| GET | `/currency/supported` | Desteklenen para birimleri | Public |

### 8.9 Currency Admin

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/admin/currency/rates` | Kur durumu + son sync | Admin |
| POST | `/admin/currency/sync` | Manuel sync tetikle | Admin |
| GET | `/admin/currency/rates/history` | 30 gün kur geçmişi | Admin |

### 8.10 System

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/up` | Health check (Laravel built-in) | Public, no proxy guard |
| GET | `/system/settings/public` | Public sistem ayarları (base_currency, supported_currencies, default_display_currency, site_name) | Public |

### 8.11 System (Admin)

ADR-016 single base currency mimarisinde admin'in base_currency, supported_currencies vb. değiştirmesi için. Backend Redis cache 5dk TTL — save sonrası invalidate.

| Method | Path | Summary | Auth |
|--------|------|---------|------|
| GET | `/admin/system/settings` | Tüm ayarlar (is_public ayrımı yok) | Admin |
| PATCH | `/admin/system/settings/{key}` | Tek ayar value güncelleme (audit log: oldValue + newValue) | Admin |

**Toplam endpoint sayısı (gerçek implementasyon):** Identity 5 + Catalog 6 + Sales 9 + Currency 2 + System 2 + System Admin 2 = **26 endpoint** (doc'taki 38 hedefi admin user CRUD + admin currency + admin order complete eksikleri ile uzlaşmadı).

---

## 9. POSTMAN COLLECTION EXPORT

### 9.1 OpenAPI → Postman

Postman OpenAPI 3.0 import'unu native destekler:

**Manuel adım:**
1. `php artisan l5-swagger:generate`
2. `storage/api-docs/api-docs.json` dosyasını kopyala
3. Postman: Import → Upload Files → seç
4. "Generate collection from imported APIs" → OK

### 9.2 Otomasyon Script

`scripts/generate-postman-collection.sh`:

```bash
#!/usr/bin/env bash
set -e

cd backend

echo "Generating OpenAPI spec..."
php artisan l5-swagger:generate

echo "Converting to Postman collection..."
# openapi-to-postmanv2 npm package
npx openapi-to-postmanv2 \
  -s storage/api-docs/api-docs.json \
  -o ../docs/postman/dehasoft.postman_collection.json \
  -p \
  -O folderStrategy=Tags,requestNameSource=Fallback

echo "Postman collection generated: docs/postman/dehasoft.postman_collection.json"
```

`package.json` (root):
```json
{
  "devDependencies": {
    "openapi-to-postmanv2": "^4.21.0"
  }
}
```

### 9.3 Postman Environment

`docs/postman/dehasoft.postman_environment.json`:

```json
{
  "id": "dehasoft-local",
  "name": "Dehasoft Local",
  "values": [
    {
      "key": "baseUrl",
      "value": "http://localhost:9000/api/v1",
      "enabled": true
    },
    {
      "key": "frontendUrl",
      "value": "http://localhost:3000/api",
      "enabled": true
    },
    {
      "key": "proxySecret",
      "value": "<your-proxy-secret>",
      "enabled": true,
      "type": "secret"
    },
    {
      "key": "accessToken",
      "value": "",
      "enabled": true,
      "type": "secret"
    },
    {
      "key": "refreshToken",
      "value": "",
      "enabled": true,
      "type": "secret"
    }
  ]
}
```

### 9.4 Auto-Save Token After Login

Postman collection'a pre-request / test script ekle (login endpoint'inde):

```javascript
// Tests tab of POST /auth/login
const response = pm.response.json();
if (response.data && response.data.accessToken) {
    pm.environment.set("accessToken", response.data.accessToken);
}
if (response.data && response.data.refreshToken) {
    pm.environment.set("refreshToken", response.data.refreshToken);
}
```

Sonraki request'lerde `Authorization: Bearer {{accessToken}}` otomatik.

### 9.5 Direct vs Proxy Test

Postman collection iki environment'la kullanılabilir:

| Environment | baseUrl | Auth | Proxy Secret |
|-------------|---------|------|--------------|
| **Direct (dev only)** | `http://localhost:9000/api/v1` | Bearer JWT | `X-Proxy-Secret` header |
| **Via Proxy** | `http://localhost:3000/api` | Cookie (auto) | Otomatik |

Production: sadece via proxy çalışır (direct backend public değil).

---

## 10. FRONTEND PROXY DOCUMENTATION

Next.js BFF endpoint'leri Laravel ile **birebir aynı** path'lerde proxy yapar. Ayrı OpenAPI dokümantasyonu **gereksiz** ama README'de açıklanmalı:

`frontend/README.md`:

```markdown
## API Proxy

All `/api/*` routes proxy to the Laravel backend.
Same paths, same payloads, same responses.

For full API reference: http://localhost:9000/api/documentation

The proxy adds:
- `X-Proxy-Secret` header (server-side, not exposed to browser)
- Cookie forwarding (access_token, refresh_token)
- Correlation ID generation

Browser → Next.js (`/api/catalog/products`) → Laravel (`/api/v1/catalog/products`)
```

---

## 11. DOCUMENTATION DEPLOYMENT

### 11.1 Local Development

```bash
docker-compose up -d
# Swagger UI: http://localhost:9000/api/documentation
# Postman: import docs/postman/dehasoft.postman_collection.json
```

### 11.2 Generate Command Workflow

```bash
# Generate OpenAPI spec
docker-compose exec backend php artisan l5-swagger:generate

# Generate Postman collection
bash scripts/generate-postman-collection.sh

# Commit if changed
git add docs/postman/ storage/api-docs/
git commit -m "docs: regenerate api documentation"
```

### 11.3 CI Auto-Generation

`.github/workflows/api-docs.yml`:

```yaml
name: API Documentation

on:
  push:
    branches: [main]
    paths:
      - 'backend/src/Presentation/**'
      - 'backend/src/Application/**/DTOs/**'

jobs:
  generate-docs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: cd backend && composer install --no-dev

      - name: Generate OpenAPI
        run: cd backend && php artisan l5-swagger:generate

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Generate Postman
        run: |
          npx openapi-to-postmanv2 \
            -s backend/storage/api-docs/api-docs.json \
            -o docs/postman/dehasoft.postman_collection.json \
            -p

      - name: Commit and push
        run: |
          git config user.name 'github-actions'
          git config user.email 'github-actions@github.com'
          git add docs/postman/ backend/storage/api-docs/
          git diff --staged --quiet || git commit -m "docs: auto-update api documentation [skip ci]"
          git push
```

### 11.4 Production Swagger Access

Production'da Swagger UI'a erişim için:

**Test için (Dehasoft değerlendirmesi):**
- `https://api.example.com/api/documentation` — public
- `BACKEND_PROXY_SECRET` validation EXEMPT (proxy bypass kuralından muaf)

```php
// routes/api.php
Route::get('/api/documentation', ...)->withoutMiddleware('proxy.origin');
Route::get('/docs/api-docs.json', ...)->withoutMiddleware('proxy.origin');
```

**Uzun vadeli production:**
- HTTP Basic Auth ile koru
- veya internal subdomain'a taşı

---

## 12. CHECKLIST

Implementation tamam sayılır eğer:

**Setup:**
- [ ] `darkaonline/l5-swagger` install edildi
- [ ] `config/l5-swagger.php` publish edildi ve özelleştirildi
- [ ] `.env`'e L5_SWAGGER_* variables eklendi
- [ ] `ApiDocumentation` root info class yazıldı
- [ ] `CommonSchemas` ortak schema'lar yazıldı

**Annotations:**
- [ ] Tüm Controller method'larına `#[OA\Get/Post/Put/Patch/Delete]` eklendi (~38 endpoint)
- [ ] Her endpoint'te `summary`, `description`, `tags`, `responses` mevcut
- [ ] Auth gerektiren endpoint'lerde `security` parametresi
- [ ] Tüm DTO'lara `#[OA\Schema]` eklendi
- [ ] Tüm FormRequest'lere `#[OA\Schema]` eklendi
- [ ] Common error schemas referansları doğru

**Generation:**
- [ ] `php artisan l5-swagger:generate` hatasız çalışıyor
- [ ] `storage/api-docs/api-docs.json` üretiliyor
- [ ] Swagger UI `/api/documentation`'da açılıyor
- [ ] "Try it out" çalışıyor (en az login + bir authenticated endpoint)

**Postman:**
- [ ] `openapi-to-postmanv2` package install
- [ ] `scripts/generate-postman-collection.sh` çalışıyor
- [ ] `docs/postman/dehasoft.postman_collection.json` üretiliyor
- [ ] `docs/postman/dehasoft.postman_environment.json` mevcut
- [ ] Login endpoint'inde auto-save token script
- [ ] Postman'a import edilince tüm endpoint'ler çalışıyor

**Documentation:**
- [ ] Root README'de Swagger link
- [ ] Root README'de Postman download link
- [ ] CI'da api-docs auto-generation aktif

**Verification:**
- [ ] Tüm endpoint'ler Swagger UI'da görünüyor
- [ ] Schema validation: request bodies + responses doğru
- [ ] Authentication flow Swagger'dan test edilebiliyor
- [ ] Postman collection environment ile çalışıyor

---

## 📌 EXAMPLES & TEMPLATES

### Quick Reference: Annotation Snippets

**GET endpoint:**
```php
#[OA\Get(
    path: '/...',
    summary: '...',
    tags: ['...'],
    responses: [
        new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(...)),
    ],
)]
```

**POST endpoint:**
```php
#[OA\Post(
    path: '/...',
    summary: '...',
    security: [['cookieAuth' => []]],
    tags: ['...'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/...'),
    ),
    responses: [
        new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(...)),
        new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
    ],
)]
```

**Path parameter:**
```php
parameters: [
    new OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid'),
    ),
],
```

---

**Last Updated:** 2026-05-04
**Version:** 1.0
**Standard:** OpenAPI 3.0
