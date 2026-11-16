# CLAUDE.md

> **AUTO-LOADED:** Claude Code bu dosyayı her oturum başında otomatik okur. **Buradaki kurallar tüm dokümanların üstündedir.** Buradaki bilgi varsa, dokümanları yeniden okuma — referansla yetin.

---

## 🎯 PROJE KİMLİĞİ

**Ad:** E-Ticaret Demo Projesi
**Tür:** 10 günlük teknik test — basit e-ticaret (auth + ürün + sepet + sipariş + multi-currency)
**Stack:** Laravel 11 + PostgreSQL 15 + Redis 7 + RabbitMQ 3.13 + Elasticsearch 8.13 + Next.js 15 + Node.js 20
**Mimari:** Onion + CQRS + DDD (Lite) + FSD (frontend) + Decoupled Logging Pipeline

---

## 📚 DOKÜMAN İNDEKSİ — HANGİ DOSYADA NE VAR

**Bir konuda iş yaparken sadece İLGİLİ dokümanı oku, hepsini değil.**

| Konu | Dosya | Bölüm Anchor'ı |
|------|-------|----------------|
| Klasör yapısı, modül listesi | `docs/PROJECT_STRUCTURE.md` | §2 Modules, §15 Repository Layout |
| FSD frontend yapısı | `docs/PROJECT_STRUCTURE.md` | §10 Frontend Architecture |
| Proxy/BFF tasarımı | `docs/PROJECT_STRUCTURE.md` | §11 Proxy Layer |
| Tablo/kolon/index detayları | `docs/DATABASE_SCHEMA.md` | §3-6 Schemas |
| Migration nasıl yazılır | `docs/DATABASE_SCHEMA.md` | §9 Migration Strategy |
| Value Object / Entity template | `docs/CODING_STANDARDS.md` | §2-3 |
| Command + Handler template | `docs/CODING_STANDARDS.md` | §6-9 |
| Repository implementation | `docs/CODING_STANDARDS.md` | §11-14 |
| Controller + FormRequest | `docs/CODING_STANDARDS.md` | §15-16 |
| BusServiceProvider mapping | `docs/CODING_STANDARDS.md` | §17 |
| Pest test pattern'leri | `docs/CODING_STANDARDS.md` | §19 |
| Forbidden patterns | `docs/CODING_STANDARDS.md` | §21 |
| RabbitMQ topology | `docs/LOGGING_SERVICE.md` | §5 |
| Audit logger usage | `docs/LOGGING_SERVICE.md` | §7.4, §7.8 |
| Node.js consumer | `docs/LOGGING_SERVICE.md` | §8 |
| Proxy origin middleware | `docs/SECURITY.md` | §2.2 |
| Token replay detection | `docs/SECURITY.md` | §4.4 |
| CSRF + Origin validation | `docs/SECURITY.md` | §4.5 |
| Resource ownership (IDOR) | `docs/SECURITY.md` | §5.3 |
| Rate limiting tablosu | `docs/SECURITY.md` | §7.2 |
| Security test checklist | `docs/SECURITY.md` | §10 |

**Kural:** Bir task başlarken sadece o task'ın gerektirdiği bölümleri oku. Tüm dokümanı yeniden okumak token israfıdır.

---

## 🗂️ DOSYA YERLEŞİM HARİTASI (Glob Patterns)

Yeni dosya yaratırken **TAM PATH'i bu pattern'lere uydur**, başka yere yazma:

### Backend (Laravel)

```
src/Domain/{Module}/Entities/*.php
src/Domain/{Module}/ValueObjects/*.php
src/Domain/{Module}/Contracts/*Interface.php
src/Domain/{Module}/Exceptions/*Exception.php
src/Domain/{Module}/Services/*.php

src/Application/{Module}/Commands/*Command.php
src/Application/{Module}/Queries/*Query.php
src/Application/{Module}/Handlers/Commands/*Handler.php
src/Application/{Module}/Handlers/Queries/*Handler.php
src/Application/{Module}/DTOs/*DTO.php

src/Infrastructure/{Module}/Models/*Model.php
src/Infrastructure/{Module}/Mappers/*Mapper.php
src/Infrastructure/{Module}/Repositories/Eloquent*Repository.php
src/Infrastructure/{Module}/Services/*.php

src/Presentation/{Module}/Controllers/*Controller.php
src/Presentation/{Module}/Requests/*Request.php
src/Presentation/{Module}/Middleware/*Middleware.php

routes/api/{module}.php
database/migrations/{module}/YYYY_MM_DD_HHMMSS_*.php

tests/Unit/Domain/{Module}/...
tests/Unit/Application/{Module}/...
tests/Feature/{Module}/...
tests/Feature/Security/...
```

### Frontend (Next.js FSD)

```
src/app/(routes)/...                  # Pages (route segments)
src/app/api/[...path]/route.ts        # Catch-all proxy
src/app/api/auth/{action}/route.ts    # Auth-specific handlers
src/pages-ui/{page}/...               # Page composition
src/widgets/{widget}/...              # Composite UI blocks
src/features/{feature}/...            # User actions (login form, add to cart)
src/entities/{entity}/...             # Business entity UI (ProductCard)
src/shared/{ui|api|lib|config}/...    # Atomic shared
```

### Log Service (Node.js)

```
log-service/src/consumers/{Audit|Application|Access}Consumer.ts
log-service/src/elasticsearch/...
log-service/src/rabbitmq/...
log-service/src/schemas/*.ts
log-service/src/lib/*.ts
log-service/tests/**/*.test.ts
```

### Infrastructure

```
infrastructure/rabbitmq/{definitions.json,rabbitmq.conf}
infrastructure/elasticsearch/templates/*.json
infrastructure/elasticsearch/ilm-policies/*.json
```

---

## ⚡ HIZLI KOD KALIPLARI (kopyala-yapıştır referans)

**Dokümanlardan tekrar üretmek zorunda değilsin** — bu pattern'leri ezbere kullan.

### Yeni Modül Eklerken Standart 13 Adım

1. Domain VO'ları (`{Entity}Id` minimum)
2. Domain Entity (private fields + behavior methods + `markAsUpdated`)
3. Domain Exception'lar (factory method'larla)
4. Repository Interface (Domain/{Module}/Contracts)
5. Eloquent Model (`final`, `$table = 'schema.table'`, schema-qualified)
6. Mapper (`toEntity` + `toPersistence`)
7. Eloquent Repository implementation
8. RepositoryServiceProvider'a binding ekle
9. Command/Query class'ları
10. Command/Query Handler'lar
11. DTO (`fromEntity`, `collection` static factory'ler)
12. BusServiceProvider'a mapping ekle
13. Controller + FormRequest + Route + Tests

### Class Modifier Hızlı Tablosu

| Tip | Modifier |
|-----|----------|
| Entity | `final class` (NOT readonly) |
| Value Object | `final readonly class` |
| Command/Query | `final readonly class` |
| DTO | `final readonly class` |
| Handler | `final class` |
| Repository (concrete) | `final class` |
| Mapper | `final class` |
| Controller | `final class` |
| FormRequest | `final class` |
| Exception | `final class` |
| Service Provider | `final class` |

### Standart Header Her PHP Dosyasında

```php
<?php

declare(strict_types=1);

namespace {Layer}\{Module}\{Folder};

// imports...

final {readonly} class {Name} {extends|implements} ...
```

### Test Kalıpları

- Domain entity test: `tests/Unit/Domain/{Module}/Entities/{Entity}Test.php` — Pest, Laravel TestCase EXTEND ETME
- Handler test: `tests/Unit/Application/{Module}/{Handler}Test.php` — Mockery ile repo mock
- Feature test: `tests/Feature/{Module}/{UseCase}Test.php` — `RefreshDatabase`, `actingAs`

---

## 🚫 EN ÇOK YAPILAN HATALAR (TEKRAR ETME)

Bu liste growing — yeni hata gördükçe ekle.

1. ❌ **Eloquent Model'de relationship/scope method'ları yazma.** Yasak. Tüm sorgular Repository'de.
2. ❌ **Domain layer'da `use Illuminate\...` yapma.** Domain framework-agnostic.
3. ❌ **`request()`, `auth()`, `app()` helper'ı handler'da kullanma.** Constructor injection zorunlu.
4. ❌ **Controller'da business logic yazma.** Sadece Command/Query yarat ve dispatch et.
5. ❌ **String ID parametresi geçme.** Domain method'lar Value Object alır (`ProductId`, `UserId`).
6. ❌ **Hardcoded HTTP status (`404`, `200`).** `HttpStatus::NOT_FOUND` kullan.
7. ❌ **`new DomainException(...)`.** Static factory method (`::withId`, `::forProduct`).
8. ❌ **`$fillable = ['*']`.** Mass assignment vulnerability.
9. ❌ **`allowed_origins => ['*']` + `supports_credentials => true`.** Critical CVE.
10. ❌ **`NEXT_PUBLIC_LARAVEL_URL`.** Backend URL ASLA browser'a sızmaz.
11. ❌ **Migration'da Schema::create + DB::statement ayrımını atla.** Partial unique index, CHECK constraint raw SQL ile.
12. ❌ **403 dönüp "bu kaynak var ama erişemezsin" sızdırmak.** IDOR'da 404 dön.
13. ❌ **`dd()`, `dump()` commit'lemek.** Asla.

---

## 🔧 ARAÇ KULLANIM POLİTİKASI

### Dosya Okumadan Önce

1. **Bu CLAUDE.md'de path/pattern var mı?** Varsa direkt aç.
2. **Doküman index'inde anchor var mı?** Varsa `Read tool` ile sadece o aralığı oku (line range belirt).
3. **Tüm dokümanı okumana gerek YOK.** Spesifik bölüm yeter.

### Glob/Grep Kullanımı

- **Önce `Glob`** ile dosyanın var olup olmadığını kontrol et (yoksa cheap fail)
- **Sonra `Read`** ile sadece gerekli aralığı oku
- **`Grep`** sadece ne aradığını netleştirememişsen — yoksa Glob daha verimli

### Bash Kullanımı

- `find` yerine `Glob` (Glob daha hızlı, ignore'ları tanır)
- `cat` yerine `Read` (Read line numbered)
- `grep -r` yerine `Grep` tool
- Test çalıştırırken: `./vendor/bin/pest tests/Feature/Identity` gibi **scope'la**, full suite çalıştırma her seferinde

### Edit vs Write

- **Mevcut dosyayı değiştiriyorsan `Edit`** (string replace)
- **Yeni dosyaysa `Write`**
- **Asla** dosyayı oku → tüm içeriği `Write` ile yaz (token israfı)

---

## 📝 SESSION DEVAMLILIĞI

Eğer önceki oturumdan devam ediyorsan:

1. `git log --oneline -20` ile son commit'leri gör
2. `git status` ile pending değişiklikleri gör
3. `cat docs/DECISIONS.md` (varsa) — mimari kararları gör
4. **Sadece** kalan task'ları yap, baştan başlama

Yeni oturum açarken kullanıcı sana hangi task'ta olduğunu söyleyecek (ör: "TASK 0.7'deyim, Identity modülünün handler'larını yaz").

---

## 🎯 ETKİLEŞİM TARZI

- **Türkçe açıklama**, İngilizce kod (variable, function, class names).
- **Her büyük adımdan sonra DUR ve özet ver.** "Ne yaptım, kaç dosya yarattım, hangi test'ler geçti." Onay bekle.
- **Belirsizlik varsa SOR.** Varsayım yapma — token israfının en kötü hali yanlış varsayımla iş bitirip baştan yapmak.
- **Dokümanlardaki bir şey mantıksız geliyorsa**, neden mantıksız olduğunu açıkla, alternatif öner — ben karar veririm.
- **10+ dakika debugging yapıyorsan dur ve durumu açıkla.**

---

## 🔁 LOOP DETECTION

Aşağıdakilerden biri olursa **DUR**:

- Aynı dosyayı 3+ kez okumuşsan → context'inde zaten var, oradan kullan
- Aynı testi 3+ kez çalıştırıp aynı şekilde fail ediyorsa → debugging stratejisi yanlış, durup düşün
- Aynı hatayı 2+ kez yapıyorsan (ör: namespace yanlış) → CLAUDE.md'yi tekrar oku
- Bir task 30+ dk sürdüyse → kullanıcıya durum raporu ver

---

## ⚙️ KOMUTLAR HIZLI REFERANS

```bash
# Backend
cd backend
./vendor/bin/pest                              # Full test suite
./vendor/bin/pest tests/Unit/Domain            # Sadece domain testler (en hızlı)
./vendor/bin/pest tests/Feature/Identity       # Bir modülün feature testleri
./vendor/bin/pest --filter=ReplayDetection     # Tek test
php artisan migrate                             # Run migrations
php artisan migrate:fresh --seed                # Reset + seed
php artisan tinker                              # REPL

# Frontend
cd frontend
pnpm dev
pnpm build
pnpm test
bash scripts/check-backend-leak.sh             # Verify no backend URL in bundle

# Log Service
cd log-service
pnpm dev
pnpm test

# Infrastructure
docker-compose up -d                            # Start everything
docker-compose logs -f log-service              # Tail consumer logs
docker-compose ps                               # Status check
docker-compose down -v                          # Reset everything (data lost)

# RabbitMQ
# Management UI: http://localhost:15672 (admin/admin)
# Kibana:        http://localhost:5601
```

---

**Last Updated:** 2026-05-04
**Bu dosyayı her major task arası refresh et — yeni öğrenilen pattern/hata buraya gelir.**
