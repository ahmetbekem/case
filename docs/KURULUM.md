# 🛠️ Kurulum Kılavuzu

> **Hedef:** Sıfırdan local geliştirme ortamını hazırlamak — clone'dan ilk e2e smoke test'e kadar.
> **Beklenen süre:** 10–15 dakika (Docker imageları cache'liyse 5 dk).

---

## 1) Önkoşullar

| Bileşen | Min sürüm | Notlar |
|---------|-----------|--------|
| Docker Desktop | 24+ | Compose v2 dahil |
| RAM | 8 GB | ES tek başına 2 GB ister |
| Disk | 10 GB | image'lar + volume |
| Boş portlar | `3030`, `5432`, `5601`, `5672`, `6379`, `8000`, `9200`, `15672` | Çakışma varsa `.env` ile değiştir |
| (Opsiyonel) PHP 8.4+ | host'ta `pest` çalıştırmak için | `composer install` da gerekir |
| (Opsiyonel) Node 20 + pnpm 9 | host'ta frontend lint/build için | |

> macOS / Windows: Docker Desktop. Linux: docker engine + compose-plugin.

---

## 2) Repoyu Klonla

```bash
git clone <REPO-URL> eticaret-demo
cd eticaret-demo
```

---

## 3) Environment Dosyalarını Hazırla

3 ayrı `.env` var; **`BACKEND_PROXY_SECRET` üçünde de aynı olmalı**.

```bash
cp .env.example                 .env
cp backend/.env.example         backend/.env
cp frontend/.env.example        frontend/.env.local
```

### Secret üret + 3 dosyaya yaz

```bash
# APP_KEY (Laravel)
docker compose run --rm backend php artisan key:generate --show
# Çıktıyı backend/.env içindeki APP_KEY=... satırına yapıştır

# JWT_SECRET
docker compose run --rm backend php -r 'echo bin2hex(random_bytes(32)) . PHP_EOL;'
# Çıktıyı backend/.env JWT_SECRET=... satırına yapıştır

# BACKEND_PROXY_SECRET (3 dosyada AYNI olmalı)
docker compose run --rm backend php -r 'echo bin2hex(random_bytes(32)) . PHP_EOL;'
# Çıktıyı .env, backend/.env ve frontend/.env.local'da BACKEND_PROXY_SECRET=... satırlarına yapıştır
```

> **Local için yeterli:** `APP_DEBUG=true`, `APP_ENV=local` zaten örnek dosyalarda. Production deploy için **mutlaka** [Production Deploy](../README.md#production-deploy) bölümünü oku.

---

## 4) Servisleri Başlat

```bash
docker compose up -d
```

9 container ayağa kalkar:

| Servis | Port (host) | Sağlık |
|--------|-------------|--------|
| `backend`        | 8000 | Laravel + PHP-FPM + nginx + supervisor |
| `frontend`       | 3030 | Next.js (production build, port 3000 internal) |
| `log-service`    | —    | Node consumer (RabbitMQ → ES) |
| `queue-worker`   | —    | Laravel queue + scheduler |
| `postgres`       | 5432 | DB |
| `redis`          | 6379 | cache + queue |
| `rabbitmq`       | 5672 / 15672 | message broker + management UI |
| `elasticsearch`  | 9200 | log store |
| `kibana`         | 5601 | log viewer |

`docker compose ps` ile sağlık kontrolü — hepsi `(healthy)` görmeli (queue-worker hariç — healthcheck tanımı yok).

---

## 5) Elasticsearch Template + ILM (ilk kalkışta bir kez)

```bash
bash scripts/setup-es.sh
```

Idempotent — index template'leri (`logs-audit`, `logs-app`, `logs-access`) ve ILM policy (`180-days-default`) yükler.

---

## 6) Database Migrate + Seed

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

Bu komut:
- 17 migration koşar (5 schema: `identity`, `system`, `catalog`, `currency`, `sales`)
- 4 seeder çalışır:
  - **SystemSettingsSeeder** — `base_currency=TRY`, `default_display_currency=TRY`, `supported_currencies=["TRY","USD","EUR"]`, `site_name`
  - **UserSeeder** — admin + customer demo hesapları
  - **ProductSeeder** — 15 ürün TRY
  - **AddressSeeder** — customer'a Ev (Kadıköy) + İş (Şişli), admin'e Ofis (Çankaya)

---

## 7) Smoke Test — Erişim Doğrulama

```bash
# Frontend BFF
curl -o /dev/null -w "frontend / → %{http_code}\n" http://localhost:3030/

# Backend
curl -o /dev/null -w "backend /up → %{http_code}\n" http://localhost:8000/up
curl -o /dev/null -w "Swagger UI → %{http_code}\n" http://localhost:8000/api/documentation

# Public locations (auth gerekmez)
curl http://localhost:3030/api/locations/cities | jq '.data | length'
# Beklenen: 81

# Public settings
curl http://localhost:3030/api/system/settings/public | jq '.data[0]'
```

---

## 8) Test Hesapları

`db:seed` sonrası iki demo hesap hazır:

| Rol | Email | Şifre | Yetki |
|-----|-------|-------|-------|
| admin | `admin@example.test` | `Admin123!` | Tüm sayfalar + admin panelleri |
| customer | `customer@example.test` | `Customer123!` | Sepet + sipariş + adres yönetimi |

> Şifreler `UserSeeder` idempotent çalışır — seed'i tekrar çalıştırırsan üzerine yazar.

---

## 9) E2E Smoke Script'leri

3 hazır akış doğrulama script'i:

```bash
# Guest cart akışı (cookie issue → add → persist → checkout block → register w/ merge)
bash scripts/smoke-guest-cart.sh

# Address management (locations → CRUD → set default → place order with snapshot)
bash scripts/smoke-address-management.sh
```

Beklenen çıktı: her adımda `✓` işareti. Fail olursa `bash -x` ile detaylı koş.

---

## 10) Frontend Erişim

| URL | Sayfa |
|-----|-------|
| http://localhost:3030/ | Anasayfa |
| http://localhost:3030/products | Ürün listesi (currency switcher) |
| http://localhost:3030/cart | Sepetim (guest erişim açık) |
| http://localhost:3030/login | Giriş |
| http://localhost:3030/profile/addresses | Adreslerim (login gerekli) |
| http://localhost:3030/admin/products | Ürün yönetimi (admin) |
| http://localhost:3030/admin/settings | Sistem ayarları (admin) |
| http://localhost:3030/site-map | Tüm route + endpoint rehberi |

---

## 11) Test Suite (opsiyonel — host'ta)

```bash
cd backend
composer install
php vendor/bin/pest
```

Beklenen: **124 PASS / 0 FAIL, 1065 assertion** (~5 sn).

> Container'da pest yok (production composer install). Test sadece host'ta.

---

## 12) Production Deploy (kısa not)

Local `.env`'i production'a kopyalama. Önce template:

```bash
cp backend/.env.production.example backend/.env.production
# secret'ları doldur (APP_KEY, JWT_SECRET, BACKEND_PROXY_SECRET, DB credentials)
bash scripts/validate-prod-env.sh backend/.env.production
# ✅ exit 0 ise deploy approved; ❌ ise eksikleri fix et
```

Tüm production checklist için: [README.md → Production Deploy](../README.md#production-deploy)

---

## 13) Troubleshooting

| Belirti | Olası Sebep + Çözüm |
|---------|---------------------|
| `docker compose up` "port already in use" | Çakışan servisi durdur veya `.env`'de `*_PORT=` değiştir |
| `php artisan migrate` "no unique constraint matching given keys" | Önceki migration'lardan kalmış; `migrate:fresh` kullan |
| Frontend build "BACKEND_PROXY_SECRET is required" | `frontend/.env.local`'de secret boş; 3. adıma dön |
| Test "Too Many Attempts (429)" | Login throttle (5/15dk); birkaç dakika bekle |
| `docker compose ps` queue-worker `Up` ama `(healthy)` etiketi yok | Healthcheck tanımı yok — normal, worker zaten çalışıyor |
| Log mesajları Kibana'ya gitmiyor | `docker compose logs log-service` kontrol; RabbitMQ healthy mi? |
| Cleanup job çalışıyor mu? | `docker compose exec backend php artisan schedule:list` — 3 cron görünmeli |

---

## 14) Faydalı Komutlar

```bash
# Backend log tail
docker compose logs -f backend

# DB shell
docker compose exec postgres psql -U app -d testproject

# Redis CLI
docker compose exec redis redis-cli

# Queue worker manuel
docker compose exec backend php artisan queue:work --once

# Cleanup job manuel tetikle
docker compose exec backend php artisan sales:clean-guest-carts

# Currency rate sync manuel
docker compose exec backend php artisan currency:sync-rates

# DB backup
bash scripts/backup-db.sh
```

---

## Sonraki Adım

[**KULLANIM.md**](./KULLANIM.md) — sistem işleyişini ve tipik akışları öğrenmek için.
