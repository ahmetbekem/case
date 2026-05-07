# 🗄️ Dehasoft Test Projesi - Database Schema

> **CRITICAL:** Bu doküman tüm tabloları, kolon tiplerini, kısıtları, ilişkileri ve indeksleri tanımlar. Migration'lar **kesinlikle** bu şemaya uymak zorundadır.

**Version:** 1.0
**DBMS:** PostgreSQL 15+
**Naming:** snake_case, plural tables, UUID v4 primary keys
**Schemas:** `identity`, `catalog`, `sales`, `currency`

---

## 📋 TABLE OF CONTENTS

1. [Conventions](#1-conventions)
2. [Schema Layout](#2-schema-layout)
3. [Identity Schema](#3-identity-schema) — users, refresh_tokens, addresses
4. [Catalog Schema](#4-catalog-schema)
5. [Sales Schema](#5-sales-schema) — carts (guest support), cart_items, orders (address snapshots), order_items
6. [Currency Schema](#6-currency-schema)
7. [System Schema](#7-system-schema) — settings ⭐ ADR-016
8. [Cross-Schema Relationships](#8-cross-schema-relationships)
9. [Indexes Summary](#9-indexes-summary)
10. [Migration Strategy](#10-migration-strategy)
11. [ER Diagram](#11-er-diagram)

---

## 1. CONVENTIONS

### 1.1 Naming

| Element | Rule | Example |
|---------|------|---------|
| Schema | lowercase, single word | `identity`, `sales` |
| Table | snake_case, **plural** | `users`, `cart_items` |
| Column | snake_case | `created_at`, `user_id` |
| Primary Key | always `id` (UUID v4) | |
| Foreign Key | `{singular}_id` | `product_id`, `user_id` |
| Boolean | `is_*` or `has_*` prefix | `is_active`, `has_paid` |
| Timestamp | `*_at` suffix | `created_at`, `deleted_at` |
| Index | `idx_{table}_{cols}` | `idx_orders_user_id` |
| Unique constraint | `uniq_{table}_{cols}` | `uniq_users_email` |
| Foreign key constraint | `fk_{table}_{col}` | `fk_orders_user_id` |

### 1.2 Common Columns

Her tabloda mevcut:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | UUID | NO | - | Primary key, app-generated (uuid_generate_v4 değil) |
| `created_at` | TIMESTAMPTZ | NO | NOW() | UTC zaman |
| `updated_at` | TIMESTAMPTZ | YES | NULL | UTC, ilk insert'te NULL |
| `deleted_at` | TIMESTAMPTZ | YES | NULL | Soft delete marker |

### 1.3 Type Standards

| Logical Type | PostgreSQL Type | Notes |
|--------------|-----------------|-------|
| ID (UUID) | `UUID` | App generates, not DB |
| Short string (name, title) | `VARCHAR(255)` | |
| Long text | `TEXT` | description, notes |
| Email | `VARCHAR(320)` | RFC 5321 max |
| Hashed password | `VARCHAR(255)` | bcrypt 60 char + buffer |
| Money amount | `DECIMAL(15,2)` | TRY/USD/EUR için yeterli |
| Currency code | `VARCHAR(3)` | ISO 4217 (TRY, USD, EUR) |
| Exchange rate | `DECIMAL(20,8)` | High precision rate storage |
| Quantity (stok, sepet) | `INTEGER` | non-negative check |
| Status enum | `VARCHAR(32)` | App enum'la doğrulanır |
| Boolean | `BOOLEAN` | `true`/`false` |
| Timestamp | `TIMESTAMPTZ` | UTC, never `TIMESTAMP` |
| IP address | `INET` | PostgreSQL native type |

### 1.4 Constraint Strategy

- ✅ Tüm FK'ler `ON DELETE RESTRICT` (cascade tehlikeli)
- ✅ Soft delete kullanılan tablolarda partial unique index: `WHERE deleted_at IS NULL`
- ✅ Money amount'larda `CHECK (amount >= 0)`
- ✅ Quantity'lerde `CHECK (quantity > 0)`
- ✅ Status enum'larında `CHECK (status IN (...))`

---

## 2. SCHEMA LAYOUT

```
testproject (database)
├── identity (schema)
│   ├── users
│   ├── refresh_tokens
│   └── addresses                ⭐ ADR-018
├── catalog (schema)
│   └── products
├── sales (schema)
│   ├── carts                    ⭐ ADR-017 (user_id nullable, guest_session_id, expires_at)
│   ├── cart_items
│   ├── orders                   ⭐ ADR-018 (delivery_address, billing_address JSONB + FKs)
│   └── order_items
├── currency (schema)
│   └── exchange_rates
└── system (schema)              ⭐ ADR-016
    └── settings
```

**Schema oluşturma migration'ı (ilk migration):**

```sql
CREATE SCHEMA IF NOT EXISTS identity;
CREATE SCHEMA IF NOT EXISTS catalog;
CREATE SCHEMA IF NOT EXISTS sales;
CREATE SCHEMA IF NOT EXISTS currency;
CREATE SCHEMA IF NOT EXISTS system;   -- ADR-016
```

---

## 3. IDENTITY SCHEMA

### 3.1 `identity.users`

Kayıtlı kullanıcılar.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `email` | VARCHAR(320) | NO | - | Login için, lowercase saklanır |
| `email_verified_at` | TIMESTAMPTZ | YES | NULL | Email verification (opsiyonel feature) |
| `password_hash` | VARCHAR(255) | NO | - | bcrypt hash |
| `full_name` | VARCHAR(255) | NO | - | Görüntüleme adı |
| `role` | VARCHAR(32) | NO | `'customer'` | Enum: `customer`, `admin` |
| `is_active` | BOOLEAN | NO | `true` | Disabled accounts |
| `last_login_at` | TIMESTAMPTZ | YES | NULL | |
| `last_login_ip` | INET | YES | NULL | |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |
| `updated_at` | TIMESTAMPTZ | YES | NULL | |
| `deleted_at` | TIMESTAMPTZ | YES | NULL | Soft delete |

**Constraints:**
- `PRIMARY KEY (id)`
- `CHECK (role IN ('customer', 'admin'))`
- `CHECK (email = LOWER(email))` — uygulamada da garantilenir

**Indexes:**
- `uniq_users_email` — `UNIQUE (email) WHERE deleted_at IS NULL`
- `idx_users_role` — `(role) WHERE deleted_at IS NULL`
- `idx_users_created_at` — `(created_at DESC)`

---

### 3.2 `identity.refresh_tokens`

JWT refresh token'lar. **Token'ın kendisi saklanmaz**, hash'i saklanır. Rotation + replay detection için tasarlandı.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `user_id` | UUID | NO | - | FK → identity.users.id |
| `token_hash` | VARCHAR(64) | NO | - | SHA-256 hex of refresh token |
| `family_id` | UUID | NO | - | Token family — rotation chain'i izlemek için |
| `expires_at` | TIMESTAMPTZ | NO | - | Token expiration (7 days from issue) |
| `revoked_at` | TIMESTAMPTZ | YES | NULL | Logout veya rotation'da set edilir |
| `replaced_by_token_id` | UUID | YES | NULL | Bu token rotate edildiğinde yenisinin id'si |
| `revoke_reason` | VARCHAR(64) | YES | NULL | `logout`, `rotated`, `replay_detected`, `admin_revoke` |
| `user_agent` | VARCHAR(512) | YES | NULL | Token issue eden client UA |
| `ip_address` | INET | YES | NULL | Token issue eden IP |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |
| `updated_at` | TIMESTAMPTZ | YES | NULL | |

**Constraints:**
- `PRIMARY KEY (id)`
- `FOREIGN KEY (user_id) REFERENCES identity.users(id) ON DELETE RESTRICT`
- `FOREIGN KEY (replaced_by_token_id) REFERENCES identity.refresh_tokens(id) ON DELETE SET NULL`
- `CHECK (revoke_reason IN ('logout', 'rotated', 'replay_detected', 'admin_revoke', 'expired'))`

**Indexes:**
- `uniq_refresh_tokens_token_hash` — `UNIQUE (token_hash)`
- `idx_refresh_tokens_user_id` — `(user_id)`
- `idx_refresh_tokens_family_id` — `(family_id)` — replay detection'da family'nin tümünü revoke için
- `idx_refresh_tokens_expires_at` — `(expires_at)` — cleanup job için

**Replay Detection Logic:**
- Bir token kullanıldığında `revoked_at` set edilir, `replaced_by_token_id` yenisini gösterir
- Eğer `revoked_at IS NOT NULL` olan bir token tekrar kullanılırsa → **çalınmış demektir** → `family_id`'deki TÜM token'lar revoke edilir → kullanıcı tüm cihazlarda logout olur
- Cleanup job: `expires_at < NOW() - 7 days AND revoked_at IS NOT NULL` olanları siler

---

### 3.3 `identity.addresses`

Kullanıcı adres rehberi (ADR-018). Bir kullanıcının max 10 aktif adresi olabilir; default delivery + default billing flag'leri DB-side garanti edilir. Sipariş anında `Address::toSnapshot()` JSONB olarak `sales.orders` tablosuna yazılır — adres sonradan silinse bile sipariş bilgisi bozulmaz (snapshot pattern).

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `user_id` | UUID | NO | - | FK → identity.users.id |
| `title` | VARCHAR(50) | NO | - | "Ev", "İş", "Yazlık" |
| `full_name` | VARCHAR(255) | NO | - | Teslimatta kullanılacak ad-soyad |
| `phone` | VARCHAR(20) | NO | - | E.164 format (+905551234567) |
| `country_code` | VARCHAR(2) | NO | `'TR'` | Şu an yalnızca TR (CHECK ile kilitli) |
| `city` | VARCHAR(100) | NO | - | İl (TurkeyLocationsService doğrular) |
| `district` | VARCHAR(100) | NO | - | İlçe |
| `neighborhood` | VARCHAR(150) | YES | NULL | Mahalle (opsiyonel; serbest input) |
| `postal_code` | VARCHAR(10) | YES | NULL | 5 haneli (regex) |
| `address_line` | TEXT | NO | - | Açık adres (CHECK char_length >= 10) |
| `address_line_2` | TEXT | YES | NULL | Apt/Daire/Kat |
| `is_default_delivery` | BOOLEAN | NO | `false` | Tek default — partial unique index |
| `is_default_billing` | BOOLEAN | NO | `false` | Tek default — partial unique index |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |
| `updated_at` | TIMESTAMPTZ | YES | NULL | |
| `deleted_at` | TIMESTAMPTZ | YES | NULL | Soft delete |

**Constraints:**
- `PRIMARY KEY (id)`
- `FOREIGN KEY (user_id) REFERENCES identity.users(id) ON DELETE RESTRICT` (user silinmeden adresleri silinmeli — soft delete ile yumuşatılır)
- `chk_addresses_country` — `CHECK (country_code = 'TR')` — multi-country için ALTER gerekir
- `chk_addresses_phone` — `CHECK (phone ~ '^[+0-9 ()\-]+$')` — defansif filter; PhoneNumber VO E.164 normalize'i daha sıkı
- `chk_addresses_address_line_length` — `CHECK (char_length(address_line) >= 10)`

**Indexes:**
- `idx_addresses_user_id` — `(user_id) WHERE deleted_at IS NULL` ⭐ partial — kullanıcı adres listingi
- `uniq_addresses_default_delivery` — `UNIQUE (user_id) WHERE is_default_delivery = true AND deleted_at IS NULL` ⭐ — DB-side tek default delivery garantisi
- `uniq_addresses_default_billing` — `UNIQUE (user_id) WHERE is_default_billing = true AND deleted_at IS NULL` ⭐

**Snapshot Pattern:**
- `Address::toSnapshot()` 12 field + `snapshotAt` ISO timestamp döner; PlaceOrderHandler `orders.delivery_address` (JSONB) ve `orders.billing_address` (JSONB) kolonlarına yazar
- FK referansı (`orders.delivery_address_id`, `orders.billing_address_id`) nullable — adres soft-delete edilse bile snapshot ayakta kalır
- Default re-pick policy: silinen adres default delivery/billing ise `findMostRecent()` ile en son aktif adres otomatik default'a promote edilir (DeleteAddressHandler)

**IDOR Protection:**
- Tüm address read/update/delete endpoint'leri `findByIdAndUser(addressId, userId)` üzerinden — başka kullanıcının adres ID'sine erişim 404 ADDRESS_NOT_FOUND (anti-enumeration)

---

## 4. CATALOG SCHEMA

### 4.1 `catalog.products`

Ürün kataloğu.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `sku` | VARCHAR(64) | NO | - | Stock keeping unit, unique |
| `name` | VARCHAR(255) | NO | - | Ürün adı |
| `description` | TEXT | YES | NULL | Detaylı açıklama |
| `price` | DECIMAL(15,2) | NO | - | Base price (TRY) |
| `currency` | VARCHAR(3) | NO | `'TRY'` | Base currency — şu anda her şey TRY |
| `stock` | INTEGER | NO | `0` | Mevcut stok |
| `is_active` | BOOLEAN | NO | `true` | Listing'de görünüp görünmeyeceği |
| `created_by` | UUID | NO | - | FK → identity.users.id (admin) |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |
| `updated_at` | TIMESTAMPTZ | YES | NULL | |
| `deleted_at` | TIMESTAMPTZ | YES | NULL | Soft delete |

**Constraints:**
- `PRIMARY KEY (id)`
- `FOREIGN KEY (created_by) REFERENCES identity.users(id) ON DELETE RESTRICT`
- `CHECK (price >= 0)`
- `CHECK (stock >= 0)`
- `CHECK (currency IN ('TRY', 'USD', 'EUR'))`

**Indexes:**
- `uniq_products_sku` — `UNIQUE (sku) WHERE deleted_at IS NULL`
- `idx_products_is_active` — `(is_active) WHERE deleted_at IS NULL`
- `idx_products_created_at` — `(created_at DESC)`
- `idx_products_name_trgm` — `(name gin_trgm_ops)` — search için (pg_trgm extension)

> **Note:** `currency` kolonu base price'ın hangi para biriminde tanımlandığını belirtir. Frontend'e döndürülürken `Currency` modülü ile USD/EUR'a convert edilir. **Tüm ürünler şu an için TRY base** olacak (Test Projesi scope).

---

## 5. SALES SCHEMA

### 5.1 `sales.carts`

Aktif sepetler. ADR-017 ile cart artık **user XOR guest session** ile identifiable: ya `user_id` ya `guest_session_id`, ikisi birden olamaz (DB-side `chk_carts_owner` CHECK constraint + entity validateOwnership).

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `user_id` | UUID | YES ⭐ | NULL | FK → identity.users.id (guest cart için NULL) |
| `guest_session_id` | VARCHAR(36) ⭐ | YES | NULL | UUID v4 — `guest_cart_token` cookie değeri |
| `status` | VARCHAR(32) | NO | `'active'` | Enum: `active`, `converted`, `abandoned` |
| `expires_at` | TIMESTAMPTZ ⭐ | YES | NULL | Guest cart 30 gün TTL; user cart için NULL |
| `converted_to_order_id` | UUID | YES | NULL | FK → sales.orders.id (sipariş verildiğinde) |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |
| `updated_at` | TIMESTAMPTZ | YES | NULL | |

**Constraints:**
- `PRIMARY KEY (id)`
- `FOREIGN KEY (user_id) REFERENCES identity.users(id) ON DELETE RESTRICT`
- `FOREIGN KEY (converted_to_order_id) REFERENCES sales.orders(id) ON DELETE SET NULL`
- `CHECK (status IN ('active', 'converted', 'abandoned'))`
- `chk_carts_owner` ⭐ — `CHECK ((user_id IS NOT NULL AND guest_session_id IS NULL) OR (user_id IS NULL AND guest_session_id IS NOT NULL))` — XOR ownership

**Indexes:**
- `uniq_carts_user_active` — `UNIQUE (user_id) WHERE status = 'active' AND user_id IS NOT NULL` ⭐ partial — user'ın tek aktif sepeti
- `uniq_carts_guest_active` ⭐ — `UNIQUE (guest_session_id) WHERE status = 'active' AND guest_session_id IS NOT NULL` partial — guest session'ın tek aktif sepeti
- `idx_carts_expires_at` ⭐ — `(expires_at) WHERE status = 'active' AND guest_session_id IS NOT NULL` partial — `sales:clean-guest-carts` cron taraması için
- `idx_carts_user_id` — `(user_id)`
- `idx_carts_status` — `(status)`

**Cleanup job:**
- `php artisan sales:clean-guest-carts` günlük 03:00; `WHERE status='active' AND guest_session_id IS NOT NULL AND expires_at <= NOW()` rows DELETE (cart_items FK ON DELETE CASCADE ile beraber gider). Abandoned/converted cart'lar dokunulmaz (analytics).

**Login/Register merge:**
- `MergeGuestCartCommand` 3 senaryo: guest cart yok no-op; user cart yok → `transferOwnership` (guest_session_id=NULL, user_id set, expires_at=NULL); ikisi de var → `CartMerger.applyToUserCart` quantity sum + OLDEST snapshot price korunur, guest cart `markAsAbandoned()`

> **Soft delete YOK:** Cart'lar `status` ile yönetilir. Order'a dönüştüğünde `status = 'converted'` olur, geçmişte tutulur (analytics için).

---

### 5.2 `sales.cart_items`

Sepetteki ürünler.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `cart_id` | UUID | NO | - | FK → sales.carts.id |
| `product_id` | UUID | NO | - | FK → catalog.products.id |
| `quantity` | INTEGER | NO | - | Adet, > 0 |
| `unit_price_snapshot` | DECIMAL(15,2) | NO | - | Sepete eklendiği andaki fiyat (TRY) |
| `currency_snapshot` | VARCHAR(3) | NO | - | Snapshot currency (TRY) |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |
| `updated_at` | TIMESTAMPTZ | YES | NULL | |

**Constraints:**
- `PRIMARY KEY (id)`
- `FOREIGN KEY (cart_id) REFERENCES sales.carts(id) ON DELETE CASCADE` ⭐ — cart silinirse item'lar da gider
- `FOREIGN KEY (product_id) REFERENCES catalog.products(id) ON DELETE RESTRICT`
- `CHECK (quantity > 0)`
- `CHECK (unit_price_snapshot >= 0)`

**Indexes:**
- `uniq_cart_items_cart_product` — `UNIQUE (cart_id, product_id)` — aynı ürün iki kez eklenemez, quantity artar
- `idx_cart_items_cart_id` — `(cart_id)`
- `idx_cart_items_product_id` — `(product_id)`

> **Snapshot rationale:** Ürün fiyatı zaman içinde değişebilir. Sepete eklendiği andaki fiyatı saklamak, kullanıcının gördüğü fiyat ile siparişe taşınan fiyatın tutarlı olmasını sağlar. Checkout'ta tekrar doğrulanır (fiyat değiştiyse kullanıcıya gösterilir).

---

### 5.3 `sales.orders`

Siparişler. **Hot path tablosu** — çok okunur, indexler kritik.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `order_number` | VARCHAR(20) | NO | - | İnsan-okunabilir: `ORD-2026-000001` |
| `user_id` | UUID | NO | - | FK → identity.users.id |
| `status` | VARCHAR(32) | NO | `'pending'` | Enum aşağıda |
| `subtotal` | DECIMAL(15,2) | NO | - | Net tutar (base currency — ADR-016) |
| `total` | DECIMAL(15,2) | NO | - | Final tutar (base currency) — şu an subtotal = total |
| `display_currency` | VARCHAR(3) | NO | - | Kullanıcının seçtiği para birimi (TRY/USD/EUR) |
| `display_total` | DECIMAL(15,2) | NO | - | display_currency cinsinden total |
| `exchange_rate_snapshot` | DECIMAL(20,8) | NO | - | Sipariş anındaki base→display kuru (immutable) |
| `delivery_address` | JSONB ⭐ | YES | NULL | ADR-018 — sipariş anındaki teslimat adresi snapshot'ı |
| `billing_address` | JSONB ⭐ | YES | NULL | Fatura adresi snapshot'ı (delivery ile aynı olabilir) |
| `delivery_address_id` | UUID ⭐ | YES | NULL | FK → identity.addresses.id (orijinal referans; soft-delete sonrası null'a düşer) |
| `billing_address_id` | UUID ⭐ | YES | NULL | FK → identity.addresses.id |
| `notes` | TEXT | YES | NULL | İsteğe bağlı sipariş notu |
| `cancelled_at` | TIMESTAMPTZ | YES | NULL | İptal zamanı |
| `cancelled_reason` | VARCHAR(255) | YES | NULL | |
| `completed_at` | TIMESTAMPTZ | YES | NULL | Tamamlanma zamanı |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |
| `updated_at` | TIMESTAMPTZ | YES | NULL | |

**Constraints:**
- `PRIMARY KEY (id)`
- `FOREIGN KEY (user_id) REFERENCES identity.users(id) ON DELETE RESTRICT`
- `FOREIGN KEY (delivery_address_id) REFERENCES identity.addresses(id) ON DELETE SET NULL` ⭐ — adres silinince FK null'a düşer; JSONB snapshot'ı her hâlükârda korur
- `FOREIGN KEY (billing_address_id) REFERENCES identity.addresses(id) ON DELETE SET NULL` ⭐
- `CHECK (status IN ('pending', 'completed', 'cancelled'))`
- `CHECK (subtotal >= 0 AND total >= 0 AND display_total >= 0)`
- `CHECK (exchange_rate_snapshot > 0)`
- `CHECK (display_currency IN ('TRY', 'USD', 'EUR'))`

**JSONB Snapshot Format (delivery_address / billing_address):**
```json
{
  "addressId": "uuid",
  "title": "Ev",
  "fullName": "Ahmet Bekem",
  "phone": "+905551234567",
  "countryCode": "TR",
  "city": "İstanbul",
  "district": "Kadıköy",
  "neighborhood": "Caferağa",
  "postalCode": "34710",
  "addressLine": "Moda Cad. No: 123",
  "addressLine2": null,
  "snapshotAt": "2026-05-07T01:39:10+00:00"
}
```

**Indexes:**
- `uniq_orders_order_number` — `UNIQUE (order_number)`
- `idx_orders_user_id_created_at` — `(user_id, created_at DESC)` ⭐ — "kullanıcının siparişleri" için composite
- `idx_orders_status` — `(status)`
- `idx_orders_created_at` — `(created_at DESC)` — admin listing
- `idx_orders_user_id_status` — `(user_id, status)` — kullanıcının pending'leri vb.
- `idx_orders_delivery_city` ⭐ — `((delivery_address->>'city')) WHERE delivery_address IS NOT NULL` — JSONB analytics ("İstanbul'a giden siparişler")

**Order Number Generation:**
- Format: `ORD-{YYYY}-{seq:06d}`
- Sequence: `sales.order_number_seq` PostgreSQL sequence
- Yıl değiştiğinde sequence reset edilmez (basitlik), sadece artarak devam eder

```sql
CREATE SEQUENCE sales.order_number_seq START 1 INCREMENT 1;

-- Application-side generation (transaction içinde):
-- SELECT 'ORD-' || EXTRACT(YEAR FROM NOW()) || '-' || LPAD(nextval('sales.order_number_seq')::text, 6, '0');
```

**Status State Machine:**
```
       ┌──────────┐
       │ pending  │ (created)
       └────┬─────┘
            │
       ┌────┴─────────────┐
       ▼                  ▼
  ┌──────────┐      ┌──────────┐
  │completed │      │cancelled │
  └──────────┘      └──────────┘
       (final)         (final)
```

> **Currency snapshot rationale:** Sipariş verildikten sonra döviz kuru değişse bile siparişin tutarı sabit kalmalı. Hem TRY base'i hem de display_currency cinsinden tutarı saklıyoruz, kuru da kayıt altında.

---

### 5.4 `sales.order_items`

Sipariş kalemleri. Sipariş anında dondurulan snapshot'lar.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `order_id` | UUID | NO | - | FK → sales.orders.id |
| `product_id` | UUID | NO | - | FK → catalog.products.id |
| `product_name_snapshot` | VARCHAR(255) | NO | - | Ürün adı snapshot |
| `product_sku_snapshot` | VARCHAR(64) | NO | - | SKU snapshot |
| `quantity` | INTEGER | NO | - | |
| `unit_price` | DECIMAL(15,2) | NO | - | Birim fiyat (TRY) |
| `line_total` | DECIMAL(15,2) | NO | - | quantity * unit_price |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |

**Constraints:**
- `PRIMARY KEY (id)`
- `FOREIGN KEY (order_id) REFERENCES sales.orders(id) ON DELETE CASCADE`
- `FOREIGN KEY (product_id) REFERENCES catalog.products(id) ON DELETE RESTRICT`
- `CHECK (quantity > 0)`
- `CHECK (unit_price >= 0 AND line_total >= 0)`

**Indexes:**
- `idx_order_items_order_id` — `(order_id)`
- `idx_order_items_product_id` — `(product_id)`

> **Snapshot rationale:** Ürün adı değişebilir veya silinebilir. Sipariş kalemi her halükarda eski adıyla okunabilmeli. `product_id` referansı sadece geriye dönük analiz için tutulur.
>
> **No `updated_at`:** Order items immutable'dır. Order cancel olsa bile item'lar değişmez.

---

## 6. CURRENCY SCHEMA

### 6.1 `currency.exchange_rates`

Frankfurter API'den günlük çekilen kurlar.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK |
| `base_currency` | VARCHAR(3) | NO | - | Source currency (TRY) |
| `target_currency` | VARCHAR(3) | NO | - | Target currency (USD, EUR) |
| `rate` | DECIMAL(20,8) | NO | - | 1 base = ? target |
| `rate_date` | DATE | NO | - | Frankfurter'ın verdiği tarih |
| `fetched_at` | TIMESTAMPTZ | NO | NOW() | Bizim çektiğimiz zaman |
| `provider` | VARCHAR(64) | NO | `'frankfurter'` | Hangi sağlayıcıdan |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |

**Constraints:**
- `PRIMARY KEY (id)`
- `CHECK (rate > 0)`
- `CHECK (base_currency IN ('TRY', 'USD', 'EUR'))`
- `CHECK (target_currency IN ('TRY', 'USD', 'EUR'))`
- `CHECK (base_currency != target_currency)`

**Indexes:**
- `uniq_exchange_rates_pair_date` — `UNIQUE (base_currency, target_currency, rate_date)` — günde bir kayıt
- `idx_exchange_rates_pair_date_desc` — `(base_currency, target_currency, rate_date DESC)` ⭐ — "en son kur" lookup

**Lookup Pattern:**
```sql
SELECT rate FROM currency.exchange_rates
WHERE base_currency = 'TRY' AND target_currency = 'USD'
ORDER BY rate_date DESC
LIMIT 1;
```

Bu sorgu Redis cache ile sarmalanır (`currency:rate:TRY:USD` key, 1 saat TTL).

> **No soft delete:** Exchange rate'ler immutable historical data. Silinmemeli.
> **No update_at:** Bir kez yazılır, değişmez. Yeni gün → yeni satır.

---

## 7. SYSTEM SCHEMA

### 7.1 `system.settings`

ADR-016 — Sistem-wide konfigürasyon (base_currency, supported_currencies, default_display_currency, site_name vb.). Natural string key (`key` UNIQUE) ile sorgulanır; `key` UUID değil — repository pattern'i AbstractEloquentRepository istisnası (DECISIONS ADR-016 notu).

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | UUID | NO | - | PK (entity Id; ama lookups `key` üzerinden) |
| `key` | VARCHAR(64) | NO | - | UNIQUE — `base_currency`, `default_display_currency`, `supported_currencies`, `site_name` |
| `value` | TEXT | NO | - | Raw değer; tip `type` kolonuyla yorumlanır (string/json/int) |
| `type` | VARCHAR(16) | NO | `'string'` | `string`, `json`, `int` (uygulama yorumu) |
| `category` | VARCHAR(32) | NO | - | UI gruplama (`currency`, `general`, `branding`) |
| `description` | TEXT | YES | NULL | Admin UI tooltip |
| `is_public` | BOOLEAN | NO | `false` | `true` ise auth gerektirmeyen `/system/settings/public` endpoint'inde dönülür |
| `created_at` | TIMESTAMPTZ | NO | NOW() | |
| `updated_at` | TIMESTAMPTZ | YES | NULL | |

**Constraints:**
- `PRIMARY KEY (id)`
- `UNIQUE (key)` — natural key
- `CHECK (type IN ('string', 'json', 'int'))`

**Cache Layer:**
- `CachedSettingRepository` decorator Eloquent repo'yu Redis ile sarar; 5 dk TTL
- `findByKey()`, `findAll()`, `findPublic()` cache hit yolu
- `save()` çağrısında 3 cache key invalidate (`system:settings:k:{key}`, `system:settings:all`, `system:settings:public`)
- Test ortamı `cache=array` driver — Redis bağımlılığı yok

**Default seeded settings (4):**
```sql
('base_currency', 'TRY', 'string', 'currency', is_public=true)
('default_display_currency', 'TRY', 'string', 'currency', is_public=true)
('supported_currencies', '["TRY","USD","EUR"]', 'json', 'currency', is_public=true)
('site_name', 'Dehasoft Test Store', 'string', 'general', is_public=true)
```

**Base Currency Change Flow (ADR-016):**
- `PATCH /admin/system/settings/base_currency` AdminSettingsController'da branch:
  - `key === 'base_currency'` → `ChangeBaseCurrencyCommand` (atomik: rate çöz → repriceAll products → abandonAllActive carts → setting update)
  - Diğer key'ler → `UpdateSettingCommand` (sadece value update)
- `system.base_currency_changed` audit event metadata: `{fromCurrency, toCurrency, rate, productsUpdated, cartsAbandoned}`

---

## 8. CROSS-SCHEMA RELATIONSHIPS

PostgreSQL şemalar arası FK destekler. Aşağıdaki cross-schema FK'ler vardır:

```
catalog.products.created_by              → identity.users.id

identity.addresses.user_id               → identity.users.id          ⭐ ADR-018

sales.carts.user_id                      → identity.users.id          (nullable — ADR-017 guest)
sales.cart_items.product_id              → catalog.products.id
sales.orders.user_id                     → identity.users.id
sales.orders.delivery_address_id         → identity.addresses.id      ⭐ ADR-018 (ON DELETE SET NULL)
sales.orders.billing_address_id          → identity.addresses.id      ⭐ ADR-018 (ON DELETE SET NULL)
sales.order_items.product_id             → catalog.products.id
```

**Migration order:** Identity → System → Catalog → Currency → Sales (dependency order)

---

## 9. INDEXES SUMMARY

Performans için kritik indeksler özeti:

| Schema.Table | Index | Purpose |
|--------------|-------|---------|
| `identity.users` | `uniq_users_email` (partial) | Login lookup |
| `identity.refresh_tokens` | `uniq_refresh_tokens_token_hash` | Token validation |
| `identity.refresh_tokens` | `idx_refresh_tokens_family_id` | Replay detection family revoke |
| `catalog.products` | `uniq_products_sku` (partial) | SKU uniqueness |
| `catalog.products` | `idx_products_is_active` (partial) | Active product listing |
| `catalog.products` | `idx_products_name_trgm` | Fuzzy search |
| `sales.carts` | `uniq_carts_user_active` (partial) | One active cart per user |
| `sales.cart_items` | `uniq_cart_items_cart_product` | Prevent duplicate items |
| `sales.orders` | `idx_orders_user_id_created_at` | User's order list (most common query) |
| `sales.orders` | `uniq_orders_order_number` | Order number lookup |
| `currency.exchange_rates` | `idx_exchange_rates_pair_date_desc` | Latest rate lookup |

### 8.1 Required PostgreSQL Extensions

```sql
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";   -- (optional, app generates UUIDs)
CREATE EXTENSION IF NOT EXISTS pg_trgm;        -- For product name fuzzy search
CREATE EXTENSION IF NOT EXISTS btree_gin;     -- Compound indexes (optional)
```

---

## 10. MIGRATION STRATEGY

### 9.1 File Naming

`database/migrations/{module}/YYYY_MM_DD_HHMMSS_{action}_{table}_table.php`

```
database/migrations/
├── 0000_setup/
│   ├── 2024_01_01_000000_create_schemas.php
│   └── 2024_01_01_000001_create_extensions.php
├── identity/
│   ├── 2024_01_01_000100_create_users_table.php
│   └── 2024_01_01_000101_create_refresh_tokens_table.php
├── catalog/
│   └── 2024_01_01_000200_create_products_table.php
├── currency/
│   └── 2024_01_01_000300_create_exchange_rates_table.php
└── sales/
    ├── 2024_01_01_000400_create_carts_table.php
    ├── 2024_01_01_000401_create_cart_items_table.php
    ├── 2024_01_01_000402_create_orders_table.php
    └── 2024_01_01_000403_create_order_items_table.php
```

### 9.2 Migration Class Structure

Her migration **sadece bir tabloyu** yaratır. Schema-qualified table names kullanılır:

```php
public function up(): void
{
    DB::statement('CREATE SCHEMA IF NOT EXISTS catalog');

    Schema::create('catalog.products', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('sku', 64);
        $table->string('name', 255);
        $table->text('description')->nullable();
        $table->decimal('price', 15, 2);
        $table->string('currency', 3)->default('TRY');
        $table->integer('stock')->default(0);
        $table->boolean('is_active')->default(true);
        $table->uuid('created_by');
        $table->timestampsTz();
        $table->softDeletesTz();

        $table->foreign('created_by')
              ->references('id')->on('identity.users')
              ->onDelete('restrict');

        $table->index('is_active', 'idx_products_is_active');
    });

    // Partial unique index (Laravel doesn't support directly)
    DB::statement('
        CREATE UNIQUE INDEX uniq_products_sku
        ON catalog.products (sku)
        WHERE deleted_at IS NULL
    ');

    DB::statement('
        CREATE INDEX idx_products_name_trgm
        ON catalog.products USING gin (name gin_trgm_ops)
    ');

    DB::statement('
        ALTER TABLE catalog.products
        ADD CONSTRAINT chk_products_price CHECK (price >= 0)
    ');

    DB::statement('
        ALTER TABLE catalog.products
        ADD CONSTRAINT chk_products_stock CHECK (stock >= 0)
    ');
}
```

### 9.3 Seeders

Sadece development/test için:

```
database/seeders/
├── DatabaseSeeder.php
├── IdentitySeeder.php           # 1 admin + 5 customer
├── CatalogSeeder.php            # 15 products
└── CurrencySeeder.php           # Initial exchange rates
```

> **Önemli:** Production'da seeder ÇALIŞTIRILMAZ. Admin user'ı manuel veya ayrı bir bootstrap komutuyla yaratılır.

---

## 11. ER DIAGRAM

```
┌─────────────────────────┐
│   identity.users        │
│─────────────────────────│
│ id (PK)                 │◄──────────┐
│ email (UNIQUE)          │           │
│ password_hash           │           │
│ full_name               │           │
│ role                    │           │
│ is_active               │           │
│ ...                     │           │
└─────────────────────────┘           │
            ▲                         │
            │ user_id                 │ user_id
            │                         │
┌─────────────────────────┐  ┌────────────────────────┐
│identity.refresh_tokens  │  │     sales.carts         │
│─────────────────────────│  │────────────────────────│
│ id (PK)                 │  │ id (PK)                │
│ user_id (FK)            │  │ user_id (FK)           │
│ token_hash (UNIQUE)     │  │ status                 │
│ family_id               │  │ converted_to_order_id  │
│ expires_at              │  └────────────────────────┘
│ revoked_at              │             │
│ replaced_by_token_id    │             │ cart_id
│ ...                     │             ▼
└─────────────────────────┘  ┌────────────────────────┐
                              │   sales.cart_items      │
                              │────────────────────────│
                              │ id (PK)                │
                              │ cart_id (FK)           │
                              │ product_id (FK) ───┐   │
                              │ quantity            │   │
                              │ unit_price_snapshot │   │
                              └────────────────────┼───┘
                                                   │
            ┌──────────────────────────────────────┤
            │                                      │
            ▼                                      ▼
┌─────────────────────────┐            ┌─────────────────────────┐
│   catalog.products      │            │     sales.orders         │
│─────────────────────────│            │─────────────────────────│
│ id (PK)                 │◄───────────│ id (PK)                 │
│ sku (UNIQUE)            │  product_id│ order_number (UNIQUE)   │
│ name                    │            │ user_id (FK)            │
│ description             │            │ status                  │
│ price                   │            │ subtotal                │
│ currency                │            │ total                   │
│ stock                   │            │ display_currency        │
│ created_by (FK→users)   │            │ display_total           │
│ ...                     │            │ exchange_rate_snapshot  │
└─────────────────────────┘            │ ...                     │
            ▲                          └─────────────────────────┘
            │ product_id                          │
            │                                     │ order_id
            │                                     ▼
            │                          ┌─────────────────────────┐
            └──────────────────────────│   sales.order_items     │
                                       │─────────────────────────│
                                       │ id (PK)                 │
                                       │ order_id (FK)           │
                                       │ product_id (FK)         │
                                       │ product_name_snapshot   │
                                       │ quantity                │
                                       │ unit_price              │
                                       │ line_total              │
                                       └─────────────────────────┘


┌─────────────────────────────┐
│  currency.exchange_rates    │
│─────────────────────────────│
│ id (PK)                     │
│ base_currency               │
│ target_currency             │  (no FK - independent table)
│ rate                        │
│ rate_date                   │
│ fetched_at                  │
│ provider                    │
└─────────────────────────────┘
```

---

## ✅ SCHEMA CHECKLIST

Migration'lar yazılırken doğrulanması gereken maddeler:

- [ ] Tüm PK'ler UUID v4 (app-generated)
- [ ] Tüm tablolarda `created_at` (TIMESTAMPTZ)
- [ ] Soft delete kullanan tablolarda `deleted_at` + partial unique index'ler
- [ ] Money kolonları `DECIMAL(15,2)` ve `CHECK >= 0`
- [ ] Quantity kolonları `CHECK > 0`
- [ ] Status enum'larında `CHECK IN (...)` constraint
- [ ] FK'lerde `ON DELETE RESTRICT` (default), kasıtlı CASCADE belgelenmiş
- [ ] Cross-schema FK'ler doğru sırada migrate ediliyor
- [ ] Composite index'ler hot path query'lere uygun
- [ ] Email kolonu lowercase store ediliyor
- [ ] `pg_trgm` extension `catalog.products` migration'ından önce yüklü
- [ ] Order number sequence yaratılmış
- [ ] Snapshot kolonları (cart_items, order_items) doğru tanımlanmış

---

**Last Updated:** 2026-05-04
**Version:** 1.0
**DBMS:** PostgreSQL 15+
