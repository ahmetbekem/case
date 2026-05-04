# Backend (Laravel 11 API)

Onion Architecture + CQRS + DDD (Lite). 4 modül: Identity, Catalog, Sales, Currency.

**Bootstrap:** TASK 0.4'te `composer create-project` ile kurulacak.

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

Detay: `../docs/CODING_STANDARDS.md`, `../docs/PROJECT_STRUCTURE.md`
