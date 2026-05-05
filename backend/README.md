# Backend (Laravel 11 API)

Onion Architecture + CQRS + DDD (Lite). 4 modül: Identity, Catalog, Sales, Currency.

**Bootstrap:** ✅ TASK 0.4 tamam — Laravel 11.51, Pest 3.8, JWT-Auth 2.3, ramsey/uuid, php-amqplib, predis kuruldu. PSR-4 autoload `Application/`, `Domain/`, `Infrastructure/`, `Presentation/` namespace'leriyle haritalandı. `src/` 4×5 layer-module hiyerarşisi hazır.

## Layer Yapısı

```
src/
├── Domain/         # Pure PHP — entities, VOs, contracts, exceptions
├── Application/    # Commands, Queries, Handlers, DTOs, Bus
├── Infrastructure/ # Eloquent repos, models, mappers, external services
└── Presentation/   # Controllers, FormRequests, middleware
```

## Bağımlılık Kuralları

- `Domain` → hiçbir şey
- `Application` → Domain
- `Infrastructure` → Domain + Application
- `Presentation` → Application (Domain'e/Infrastructure'a doğrudan erişim YASAK)

## Komutlar

```bash
./vendor/bin/pest                              # Full test suite
./vendor/bin/pest tests/Unit/Domain            # En hızlı
./vendor/bin/pest tests/Feature/Identity       # Tek modül
php artisan migrate
php artisan migrate:fresh --seed
```

## API Documentation

OpenAPI 3.0 + Swagger UI L5-Swagger üzerinden. Annotation'lar
`src/Presentation/**/Controllers/*.php` (endpoint) ve
`src/Application/**/DTOs/*.php` + `src/Presentation/**/Requests/*.php`
(Schema) altında PHP 8 attributes formatında.

```bash
# OpenAPI spec yenile (annotations değişince)
php artisan l5-swagger:generate
# → storage/api-docs/api-docs.json güncellenir

# Postman collection yenile (root'tan)
cd .. && bash scripts/generate-postman-collection.sh
# → docs/postman/dehasoft.postman_collection.json
```

Development env'de `L5_SWAGGER_GENERATE_ALWAYS=true` her request'te
otomatik regenerate eder, manuel komut gereksizdir.

**Eklemek için:** Yeni endpoint annotation pattern'i için
`../docs/API_DOCUMENTATION.md §4.2` örneklerine bak. Yeni DTO Schema'sı
için `§5.1` örneği var. Endpoint'in response'una `ref` ile ortak
schema'lara (ValidationError/UnauthorizedError/...) bağlanmayı unutma.

Detay: `../docs/CODING_STANDARDS.md`, `../docs/PROJECT_STRUCTURE.md`,
`../docs/API_DOCUMENTATION.md`
