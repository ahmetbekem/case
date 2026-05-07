# 📖 Kullanım Kılavuzu

> **Hedef:** Sistemi kurduktan sonra hangi akışları nasıl test edersin — customer flow, admin flow, debug ve gözlem.

> Önkoşul: [KURULUM.md](./KURULUM.md) tamamlanmış, `http://localhost:3030` 200.

---

## 1) Demo Akışlar — Hızlı Tur

### Akış A — Misafir Kullanıcı (Login Yok)

ADR-017 Guest Cart — login zorunlu değil; sadece sipariş için auth.

1. Browser **incognito** aç → http://localhost:3030/products
2. Bir ürün seç → **"Sepete Ekle"** tıkla
   - DevTools → Application → Cookies → `guest_cart_token` UUID görünür (HttpOnly)
3. Header'da sepet sayacı `1` görünür
4. http://localhost:3030/cart → ürün listede; quantity arttır → backend snapshot
5. **"Giriş Yap ve Sipariş Ver"** tıkla → /login?returnUrl=/checkout
6. Login (`customer@dehasoft.test` / `Customer123!`)
7. Auto-redirect → /checkout
   - URL'de `?merged=1` görünür → "Misafir sepetiniz hesabınızla birleştirildi" yeşil banner
   - Sepet hala dolu — guest cart user'a transfer edildi
8. Default adres otomatik seçili (Ev — Kadıköy)
9. **"Siparişi Onayla"** → /orders/{id}
10. Logout → cart icon `0` (user cart artık yok, yeni guest cookie üretilecek)

### Akış B — Login'li Kullanıcı (Customer)

1. http://localhost:3030/login → customer login
2. http://localhost:3030/profile/addresses → 2 adres (Ev + İş) görünür
3. **"Yeni Adres"** → form
   - İl dropdown: 81 il (TurkeyLocationsService)
   - İl seç → ilçe dropdown otomatik güncellenir
   - Telefon `+90 555 123 45 67` formatında — backend `+E.164` normalize eder
4. **"Varsayılan Teslimat"** badge ile mevcut default'u değiştir
5. Ürün ekle sepete → /checkout → fatura adresi seçici (varsayılan: delivery ile aynı checkbox)
6. Sipariş ver → /orders/{id} → sipariş detayında adres snapshot'ı görünür
7. Adres sil → adres rehberinden gider, ama eski sipariş hala adresi gösterir (snapshot pattern)

### Akış C — Admin Akışı

1. Login (`admin@dehasoft.test` / `Admin123!`)
2. Header'da **"Admin"** dropdown → "Ürün Yönetimi" + "Sistem Ayarları"
3. **/admin/products** — inline ekle/sil/stok ayarla
   - Currency picker **YOK** (ADR-016 tek base currency)
   - Fiyat input'ta sadece sayı; `base_currency` setting'inde (TRY) tutulur
4. **/admin/settings** — sistem ayarları
   - `site_name` "Yeni Adı" yap → Kaydet → SweetAlert success
   - `base_currency` TRY → USD yap → **SweetAlert warning**:
     > "Tüm ürünlerin fiyatları yeni kura göre yeniden hesaplanacak ve aktif sepetler sıfırlanacak. Bu işlem geri alınamaz."
   - **"Evet, fiyatları güncelle"** → atomic transaction:
     - Tüm 15 ürün TRY → USD repriceall (kur ile çevirim)
     - Tüm aktif cart'lar `abandoned` (mixed currency snapshot koruması)
     - Setting güncellenir + Redis cache invalidate
   - SweetAlert success: `{rate, productsUpdated, cartsAbandoned}` özet

---

## 2) Akış Doğrulama — Hazır Smoke Script'leri

```bash
# Guest cart full e2e (cookie → add → persist → checkout block → register w/ merge)
bash scripts/smoke-guest-cart.sh

# Address management (locations → CRUD → set default → place order with snapshot)
bash scripts/smoke-address-management.sh
```

Beklenen: her step `✓`, son satır `OK` mesajı. Fail olursa script ham response'u gösterir.

---

## 3) API Test

### Swagger UI (en hızlı)

http://localhost:8000/api/documentation

- 26 path, 35 schema, 10 tag
- "Try it out" → Authorize → Bearer token (login response'tan)
- Address endpoint'leri: tag **"Address"**
- Locations: tag **"Locations"** (auth gerekmez)
- Sistem ayarları: **"System"** (public) + **"System (Admin)"**

### Postman

```bash
# Collection import
ls docs/postman/
# dehasoft.postman_collection.json + dehasoft.postman_environment.json
```

- Login request'inde otomatik token-save script var → sonraki Bearer auth-aware request'ler hazır
- Tüm 34 endpoint export edilmiş

### Curl (CI/script-friendly)

```bash
# Login + cookie jar
JAR=$(mktemp)
curl -s -X POST -H "Content-Type: application/json" -c "$JAR" \
  -d '{"email":"customer@dehasoft.test","password":"Customer123!"}' \
  http://localhost:3030/api/auth/login | jq

# Adres listesi
curl -s -b "$JAR" http://localhost:3030/api/identity/addresses | jq '.data | length'

# Yeni adres
curl -s -b "$JAR" -X POST -H "Content-Type: application/json" \
  -d '{"title":"Test","fullName":"Test User","phone":"+905554443322","city":"İstanbul","district":"Beşiktaş","addressLine":"Test caddesi 123"}' \
  http://localhost:3030/api/identity/addresses | jq

# Cart guest mode
curl -s http://localhost:3030/api/sales/cart | jq '{isGuest, sid: .data.guestSessionId}'
```

### Yaygın Endpoint'ler

| Method | Path | Auth | Açıklama |
|--------|------|------|----------|
| GET | `/api/auth/me` | Cookie | Mevcut kullanıcı |
| GET | `/api/locations/cities` | — | 81 TR ili |
| GET | `/api/locations/cities/{city}/districts` | — | İlçeler |
| GET | `/api/sales/cart` | Optional (user/guest) | Aktif sepet |
| POST | `/api/sales/cart/items` | Optional | Sepete ekle (rate-limit 30/dk) |
| GET | `/api/identity/addresses` | User | Adres listesi |
| POST | `/api/identity/addresses` | User | Yeni adres |
| PATCH | `/api/identity/addresses/{id}/default-delivery` | User (own) | Varsayılan teslimat |
| POST | `/api/sales/orders` | User | `deliveryAddressId` zorunlu |
| GET | `/api/system/settings/public` | — | Public ayarlar |
| PATCH | `/api/admin/system/settings/{key}` | Admin | base_currency special: SweetAlert |

Tam liste: [API_DOCUMENTATION.md](./API_DOCUMENTATION.md)

---

## 4) Logları İnceleme — Kibana

http://localhost:5601

> İlk girişte data view yok. **Stack Management → Data Views → Create** → pattern: `logs-*` → time field: `@timestamp`.

### Yararlı Sorgular

```
# Tüm address eventleri
payload.action: "identity.address_*"

# Base currency değişimi audit'i (rate, productsUpdated, cartsAbandoned ile)
payload.action: "system.base_currency_changed"

# Guest cart merge (login sonrası)
payload.action: "sales.cart_transferred_from_guest"

# Security olayları
payload.action: "security.*"

# Belirli kullanıcının siparişleri
payload.actor: "userId:*" AND payload.action: "sales.order_place"
```

### Audit Event Kategorileri

| Kategori | Action prefix | Örnek metadata |
|----------|---------------|----------------|
| identity | `identity.*` | email, role |
| sales (cart) | `sales.cart_*` | productId, quantity, isGuest |
| sales (order) | `sales.order_*` | orderNumber, deliveryCity, deliveryDistrict |
| catalog | `catalog.product_*` | sku, changes |
| system | `system.*` | setting, oldValue, newValue, rate |
| security | `security.*` | reason, attemptedKey |

> Audit chain hiçbir password/token leak ETMEZ — login event metadata `[]` boş döner (secure by default).

---

## 5) Cleanup Jobs

3 scheduled cron `php artisan schedule:list`:

| Schedule | Komut | Amaç |
|----------|-------|------|
| 06:00 | `currency:sync-rates` | Frankfurter'dan TRY-base kur çek |
| 03:00 | `sales:clean-guest-carts` | 30+ gün eski guest cart'ları sil |
| 02:00 | `bash /var/www/scripts/backup-db.sh` | DB backup (rolling 30d + monthly archive) |

### Manuel Tetikleme

```bash
docker compose exec backend php artisan currency:sync-rates
docker compose exec backend php artisan sales:clean-guest-carts
bash scripts/backup-db.sh
```

> Production'da Laravel scheduler'ı işletmek için cron eklenmeli:
> `* * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1`

---

## 6) RabbitMQ Management

http://localhost:15672 — `admin / admin`

| Queue | Beklenen | Anlam |
|-------|----------|-------|
| `logs.audit.queue` | 0 mesaj, 1 consumer | Sync işlenir |
| `logs.app.queue` | 0 mesaj, 1 consumer | Async, hızlı drain |
| `logs.access.queue` | 0 mesaj, 1 consumer | Async |
| `logs.dlq` | İdeal: 0 | Birikirse log-service crash → kontrol et |

DLQ'da mesaj birikirse:
```bash
docker compose logs log-service | tail -50
docker compose exec backend php artisan logging:replay-audit-fallback
```

---

## 7) Backup + Restore

### Backup üret
```bash
bash scripts/backup-db.sh
ls backups/daily/        # rolling 30 gün
ls backups/monthly/      # ayın 1'inde archive
```

### Restore (testproject_restore_test örneği)
```bash
docker compose exec postgres createdb -U dehasoft testproject_restore_test
gunzip -c backups/daily/testproject_*.sql.gz \
  | docker compose exec -T postgres psql -U dehasoft -d testproject_restore_test
docker compose exec postgres psql -U dehasoft -d testproject_restore_test \
  -c "SELECT count(*) FROM identity.addresses;"
```

### Production S3 sync
`BACKUP_S3_BUCKET` env varsa script aws s3 sync yapar (production deploy'da set et).

---

## 8) Test Suite

```bash
cd backend
php vendor/bin/pest                       # Tüm suite (~5 sn, 124 PASS)
php vendor/bin/pest --filter=Address      # Sadece Address tests
php vendor/bin/pest --parallel            # Çok core'da hızlı
```

Test dosyaları:
- `tests/Unit/Domain/` — pure PHP (framework boot edilmez)
- `tests/Unit/Application/` — Mockery (CreateProduct, RefreshToken, UpdateSetting handler'ları)
- `tests/Feature/` — Laravel TestCase + RefreshDatabase trait

Test count breakdown: [CODE_REVIEW_REPORT.md](./CODE_REVIEW_REPORT.md#-metri̇kler)

---

## 9) Tipik Hata Senaryoları

### Stale guest cart
- Cookie expire (30 gün), browser fresh GET /cart → yeni cookie üretilir, cart boş

### Çakışan login throttle (429)
- 5 yanlış login attempt sonrası 15 dk lockout
- Bypass'ı: `docker compose exec redis redis-cli FLUSHALL` (cache + throttle counters siler)

### Base currency change "STALE_EXCHANGE_RATE"
- Kur > 7 gün eski → 409. Çözüm:
  ```bash
  docker compose exec backend php artisan currency:sync-rates
  ```

### Mixed currency cart "MIXED_CURRENCY_CART"
- Eski seed verisinde mixed currency varsa checkout 409 atar
- Çözüm: cart'ı boşalt + tek currency'de yeniden ekle (veya admin base_currency tek currency'e align et)

---

## 10) Geliştirici Notları

- **Konvansiyon dokümanları:**
  - [PROJECT_STRUCTURE.md](./PROJECT_STRUCTURE.md) — modül layout
  - [CODING_STANDARDS.md](./CODING_STANDARDS.md) — naming + patterns
  - [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) — tablo yapısı + ER diagram
  - [SECURITY.md](./SECURITY.md) — threat model + mitigations
  - [LOGGING_SERVICE.md](./LOGGING_SERVICE.md) — RabbitMQ → ES pipeline
  - [FRONTEND_GUIDE.md](./FRONTEND_GUIDE.md) — FSD pattern + page envantar
- **Mimari kararlar:** [DECISIONS.md](../DECISIONS.md) — 18 ADR
- **Code review:** [CODE_REVIEW_REPORT.md](./CODE_REVIEW_REPORT.md) — Phase 1-10 detay rapor

---

## Sonraki Adım

[**TANITIM.md**](./TANITIM.md) — sistem mimarisi ve modül özetleri.
