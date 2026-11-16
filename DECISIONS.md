# 📜 DECISIONS.md - Architectural Decision Record

> **PURPOSE:** Bir karar bir kez verilir, bir daha tartışılmaz. Bu dosya kararları kaydeder. Claude Code yeni bir karar verirken **önce buraya bakar** — zaten verilmiş kararı sorgulamaz.

> **TOKEN İSRAFINI ÖNLEME:** Aynı konuyu (örn: "REST mi RPC mi?") tekrar tartışmak demek tüm dokümanları tekrar okumak demek. Burada hızlıca kontrol et, geç.

---

## 📋 ADR FORMAT

Her karar şu yapıda:

```
## ADR-XXX: {Karar Başlığı}
**Status:** Accepted | Superseded by ADR-YYY | Deprecated
**Date:** YYYY-MM-DD
**Context:** Hangi sorunu çözüyor
**Decision:** Ne yapılacak
**Rationale:** Neden bu seçildi
**Consequences:** Hangi kısıtlar/avantajlar geliyor
**Alternatives Considered:** Hangi seçenekler reddedildi ve niye
```

---

## ADR-001: Onion Architecture + CQRS Kullanılacak

**Status:** Accepted
**Date:** 2026-05-04

**Context:** Test projesi 10 günlük olmasına rağmen test eden firma "enterprise-grade architecture" bekliyor. Basit MVC yetersiz, ama tam DDD aggregate'leri overkill.

**Decision:** Onion Architecture + CQRS Lite uygulanacak. Domain/Application/Infrastructure/Presentation layer'ları net ayrılacak. Her use case Command veya Query olarak modellenecek.

**Rationale:**
- Test eden için profesyonel görünüm
- Test edilebilirlik (Domain saf PHP, framework-agnostic)
- Modülerlik (Identity, Catalog, Sales, Currency birbirine bağlı değil)

**Consequences:**
- ✅ Yüksek kod kalitesi
- ✅ Test coverage kolay
- ❌ Boilerplate fazla (Command + Handler + DTO + ...)
- ❌ Yeni kişi onboarding 1-2 gün

**Alternatives Considered:**
- Plain Laravel MVC → enterprise expectation'a uymuyor
- Full DDD with aggregates → 10 günde overkill, test scope'a sığmaz

---

## ADR-002: PostgreSQL, Tek Veritabanı, Modül Başına Schema

**Status:** Accepted
**Date:** 2026-05-04

**Context:** Modüllerin DB'de izolasyonu nasıl olacak?

**Decision:** Tek PostgreSQL veritabanı, modül başına PostgreSQL schema (`identity`, `catalog`, `sales`, `currency`). Cross-schema FK izinli ama dikkatli kullanılacak.

**Rationale:**
- Mantıksal izolasyon (`identity.users` ≠ `sales.users` karışıklığı yok)
- Tek veritabanı = tek backup = basit ops
- Cross-schema query mümkün (admin paneli için)

**Consequences:**
- ✅ Net mantıksal sınırlar
- ✅ Migration modül başına klasörlenebilir
- ❌ Eloquent `$table = 'schema.table'` syntax'ı standart-dışı

**Alternatives Considered:**
- Tek schema, tablo prefix'i (örn: `identity_users`) → daha az clean
- Ayrı veritabanı (mikroservis tarzı) → 10 gün için overkill

---

## ADR-003: JWT (Access) + Refresh Token Rotation, httpOnly Cookies

**Status:** Accepted
**Date:** 2026-05-04
**Detayed in:** `docs/SECURITY.md` §4

**Decision:**
- Access token: 15 dakika, JWT, httpOnly cookie
- Refresh token: 7 gün, opaque token (DB'de hashed), httpOnly cookie
- Rotation: her refresh'te yeni refresh token + family tracking
- Replay detection: kullanılmış refresh tekrar denenirse tüm family revoke

**Rationale:**
- httpOnly = XSS'e dirençli (localStorage value)
- Rotation = uzun süreli refresh token'ın kaybolma riskini azaltır
- Replay detection = token çalınması durumunda otomatik mitigation

**Consequences:**
- ✅ Yüksek güvenlik
- ✅ KVKK/GDPR uyumlu audit trail
- ❌ Concurrent refresh için 5sn grace window gerekir (race condition)
- ❌ Mobile app future support'u için JWT'ye Authorization header fallback gerekecek

**Alternatives Considered:**
- localStorage'da JWT → XSS'e açık, REJECTED
- Sadece access token, refresh yok → 15dk'da bir login = UX kötü
- Stateful session (Laravel default) → frontend stateless olmuyor

---

## ADR-004: Next.js BFF (Proxy) Zorunlu, Direkt Backend Erişimi Yasak

**Status:** Accepted
**Date:** 2026-05-04
**Detayed in:** `docs/PROJECT_STRUCTURE.md` §11, `docs/SECURITY.md` §2-3

**Decision:** Frontend (browser) ASLA Laravel API'ye doğrudan istek atmaz. Tüm istekler Next.js BFF üzerinden geçer. Laravel public erişime kapalıdır.

**Rationale:**
- Test brifinde "API endpoint exposure" güvenlik kriteri
- BFF cookie management'ı kolaylaştırır
- Backend versioning frontend'e sızmıyor

**Consequences:**
- ✅ Backend internet'te yok = saldırı yüzeyi minimum
- ✅ Cookie set/get operasyonları Next.js'de
- ❌ Her endpoint için ya catch-all ya dedicated route handler gerekiyor
- ❌ Frontend dev için bile Next.js gerekli (direkt curl Laravel'e atılmaz)

**Alternatives Considered:**
- Direct browser → Laravel + CORS → "API endpoint exposure" kriterine takılır
- API Gateway (Kong/Traefik) → 10 gün için overkill

---

## ADR-005: RabbitMQ + Node.js + Elasticsearch Logging Pipeline

**Status:** Accepted
**Date:** 2026-05-04
**Detayed in:** `docs/LOGGING_SERVICE.md`

**Decision:** Laravel doğrudan ES'e yazmaz. RabbitMQ exchange'e publish eder, Node.js consumer batch'leyip ES'e yazar.

**Rationale:**
- Laravel response latency etkilenmez
- ES down olunca app çalışmaya devam eder
- Bulk index 10-100x daha verimli
- StkPro pattern'i — kanıtlanmış

**Consequences:**
- ✅ Decoupled, scalable
- ✅ Audit logs ASLA kaybolmaz (publisher confirms + fallback)
- ❌ Operasyon kompleksitesi artıyor (4 yeni service)
- ❌ Local dev için bile RabbitMQ + ES gerekli

**Alternatives Considered:**
- Direct ES write → latency, coupling
- File log + Filebeat → daha az kontrol, structured logging zor
- Cloud logging service (Datadog, etc.) → cost, vendor lock

---

## ADR-006: Pest 3.x Test Framework

**Status:** Accepted
**Date:** 2026-05-04

**Decision:** PHPUnit yerine Pest 3.x kullanılacak.

**Rationale:**
- Modern, okunabilir syntax (`it('does X', fn() => ...)`)
- Laravel ekosisteminde 2024+ standart
- Pest plugin'leri (Laravel, Mock) ekstra fonksiyonalite

**Consequences:**
- ✅ Daha temiz test kodu
- ✅ Daha hızlı yazım
- ❌ Junior developer öğrenme eğrisi var

**Alternatives Considered:**
- PHPUnit → daha mature ama verbose

---

## ADR-007: Frontend FSD (Feature-Sliced Design)

**Status:** Accepted
**Date:** 2026-05-04
**Detayed in:** `docs/PROJECT_STRUCTURE.md` §10

**Decision:** Next.js src dizini FSD pattern'iyle organize edilecek: app → pages-ui → widgets → features → entities → shared.

**Rationale:**
- Domain modelleme frontend'de de net (entities/features ayrımı)
- Backend modülleriyle paralel: `entities/product` ↔ `Catalog/Product`
- Re-usability ve kod organizasyonu standart

**Consequences:**
- ✅ Net layer'lar
- ❌ Junior dev için öğrenme eğrisi var
- ❌ Component'in nereye gideceği bazen tartışılır (widgets vs features)

**Alternatives Considered:**
- Flat `components/` klasörü → büyük projede kaos
- Atomic Design → daha çok UI library için

---

## ADR-008: 4 Modül — Identity, Catalog, Sales, Currency

**Status:** Accepted
**Date:** 2026-05-04

**Decision:** Sales modülü Cart + Order birleşik. Ayrı modül değil.

**Rationale:**
- Cart aslında Order'ın "draft state"i
- Aynı User'a aittir, aynı items konseptini paylaşır
- 10 günlük scope'ta 5 modül 4'ten daha karmaşık

**Consequences:**
- ✅ Daha az boilerplate
- ✅ PlaceOrderHandler cart'ı doğrudan kullanır, cross-module call yok
- ❌ Ileride Order Management ayrılırsa refactor gerekir

**Alternatives Considered:**
- Cart ve Order ayrı modül → cross-module event/dispatch gerekir, overkill

---

## ADR-009: Frankfurter API for Currency Rates

**Status:** Accepted
**Date:** 2026-05-04

**Decision:** Currency exchange rate'leri Frankfurter API'den (frankfurter.app) çekilecek.

**Rationale:**
- ECB-based, güvenilir
- API key yok, rate limit yok
- Free, açık kaynak

**Consequences:**
- ✅ Setup zero-friction
- ❌ Sadece günlük rate (intra-day yok) — Test Projesi için yeterli
- ❌ Eğer kapanırsa fallback gerekir

**Alternatives Considered:**
- exchangerate-api.com → API key, rate limit
- TCMB → sadece TRY-base, format zor parse
- CurrencyLayer, FxRates → paid

---

## ADR-010: Pricing Snapshot in Cart Items + Order Items

**Status:** Accepted
**Date:** 2026-05-04
**Detayed in:** `docs/DATABASE_SCHEMA.md` §5.2, §5.4

**Decision:** Cart item'ı sepete eklendiği andaki fiyatı snapshot olarak tutar. Sipariş anında order_item'a tekrar dondurulur.

**Rationale:**
- Ürün fiyatı sepetteyken değişebilir, kullanıcı görmediği fiyatı ödeyemez
- Order items immutable — geçmişte ne kadar ödedik kayıt altında

**Consequences:**
- ✅ Audit-friendly (her sipariş tarihçesi net)
- ❌ Storage maliyeti artar (her item'a fiyat kopyalanır)
- ❌ Checkout'ta fiyat değiştiyse re-confirmation gerekir

**Alternatives Considered:**
- Item product_id ile join, current price kullan → fiyat değişimi inconsistency yaratır

---

## ADR-011: UUID v4 PK, App-Generated

**Status:** Accepted
**Date:** 2026-05-04

**Decision:** Tüm primary key'ler UUID v4. Database tarafında değil, application tarafında generate edilecek (`Ramsey\Uuid`).

**Rationale:**
- Distributed systems-ready (microservice migration'da kolay)
- Insert öncesi ID bilinir (event sourcing'e hazır)
- Sequence enumeration attack'larını engeller

**Consequences:**
- ✅ Cross-system unique
- ✅ Predictable ID at insert time
- ❌ Index storage 16 byte (BIGINT 8 byte) — 2x maliyet
- ❌ Sıralı değil — index lokality kötü, ama Test Projesi scale'inde unrelevant

**Alternatives Considered:**
- BIGINT auto-increment → enumeration risk
- ULID → daha iyi sıralama ama PostgreSQL native UUID type'ı kazandı

---

## ADR-012: PostgreSQL TIMESTAMPTZ + UTC

**Status:** Accepted
**Date:** 2026-05-04

**Decision:** Tüm timestamp kolonları `TIMESTAMPTZ`. Application UTC'de yazar, presentation layer'da kullanıcı timezone'una çevrilir.

**Rationale:**
- DST geçişleri sorun yaratmaz
- Kullanıcılar farklı zaman dilimindeyse doğru görüntüleme
- Industry standard

**Consequences:**
- ✅ Timezone-safe
- ❌ `TIMESTAMP` (without TZ) yerine yazılması gerek — gözden kaçabilir

---

## ADR-013: Soft Delete Sadece Belirli Tablolarda

**Status:** Accepted
**Date:** 2026-05-04

**Decision:** Soft delete sadece `users`, `products`'ta. `cart_items`, `order_items`, `exchange_rates`, `refresh_tokens`'da hard delete.

**Rationale:**
- Order/cart history immutable, hard delete yapmıyoruz zaten
- Refresh token expired olunca tutmak gereksiz storage
- Exchange rate immutable historical data

**Consequences:**
- ✅ Storage'i şişirmiyor
- ❌ "Yanlışlıkla sildim" recovery zor — ama backup var

---

## ADR-014: Shared Secret + Network Isolation (Defense-in-Depth)

**Status:** Accepted
**Date:** 2026-05-04
**Detayed in:** `docs/SECURITY.md` §2

**Decision:** Backend hem network seviyesinde izole olacak (production'da public IP yok) hem de application seviyesinde proxy secret doğrulayacak.

**Rationale:**
- Tek katman bypass edilse bile diğeri korur
- Network misconfig (örn: Docker port leak) tek başına vulnerability oluşturmaz

**Consequences:**
- ✅ Robust güvenlik
- ❌ Local dev'de bile proxy secret gerekli (Next.js'siz curl çalışmaz)

---

## ADR-015: Audit Log Sync, Application+Access Log Async

**Status:** Accepted
**Date:** 2026-05-04
**Detayed in:** `docs/LOGGING_SERVICE.md` §4

**Decision:** Audit log'lar publisher confirm + fallback ile sync. Application ve Access log'lar Laravel Queue (Redis) üzerinden async.

**Rationale:**
- Audit kaybolamaz (compliance)
- Application/Access yüksek volume, sync olursa latency

**Consequences:**
- ✅ Doğru SLA per-category
- ❌ Iki farklı pattern (sync + async) — implementation kompleksitesi

---

## ADR-016: Single Base Currency System

**Status:** Accepted
**Date:** 2026-05-06

**Context:** Product fiyatları her ürün için ayrı ayrı `currency` (TRY/USD/EUR) ile tutuluyordu. Bu, mixed-currency cart'larda subtotal hesaplamayı bozdu (1 USD ürün + 1 TRY ürün toplamı tek bir currency'e çevirmek için runtime conversion zincirleri gerekti). Admin "tek tıkla TRY → USD geç" akışı yoktu; product seed runtime'da farklı currency'ler taşıyordu.

**Decision:**
- Sistemde **tek bir base currency** var (`system.settings` → `base_currency`); tüm `catalog.products.price` bu currency'de tutulur
- Frontend dönüşüm yalnızca **display layer**'da; user seçtiği `displayCurrency` ile UI çevirir, persistence'a hiç dokunmaz
- Admin base_currency değiştirirse `ChangeBaseCurrencyHandler` atomik şekilde:
  - (oldBase → newBase) kuru çözer (direct → inverse → TRY pivot)
  - Stale guard: rate > 7 gün ise reddet
  - Tek transaction: products.repriceAll + carts.abandonAllActive + setting update
- Frontend'de admin product formunda **currency picker yok** — sadece "Fiyat (base_currency)" tek input

**Rationale:**
- Mixed currency mental model karmaşası yok — ürün tek currency'de, dönüşüm display
- `system.base_currency_changed` audit event tek karar geçişini izleyebilir
- Sales/order matematiği hep tek currency tabanlı; checkout'ta sadece displayTotal snapshot
- Analytics raporları (sum/avg) doğrudan SQL agg'a uygun
- KVKK + mali kayıt için historical order'larda `exchangeRateSnapshot` korunur

**Consequences:**
- ✅ Cart/order matematiği tek currency, hata yüzeyi azaldı
- ✅ Admin base currency değişimi guarded + atomic + audit'lı
- ✅ Frontend admin form basitleşti (currency picker yok)
- ❌ Multi-region deployment için refactor gerekir (per-region base_currency)
- ❌ ADR-016 öncesi mixed currency cart'lar `system/2024_01_06_000001_align_product_currency_with_base.php` migration'ında tek currency'e align edildi
- ❌ Stale rate guard CI'da `currency:sync-rates` cron'unun çalıştığını varsayar; offline ortamda admin base currency değiştiremez

**Alternatives Considered:**
- Per-product currency (mevcut model) → mental load + checkout uniform-check overhead
- USD base + display-time conversion → hızlı ama TRY ürünler için varsayılan currency kaymış olur (UX confusion)
- Multi-base (region tablosu + per-region price) → 10 günlük scope için overkill

---

## ADR-017: Guest Cart with Session-Based Identification

**Status:** Accepted
**Date:** 2026-05-06

**Context:** Login zorunluluğu konversiyon oranını düşürür. Modern e-ticarette (Amazon, Trendyol, Hepsiburada) guest cart standart UX pattern'i. Anonim kullanıcı sepete ürün ekleyebilmeli, sadece checkout (sipariş verme) anında auth zorunlu olmalı. Login sonrası guest cart, user'ın mevcut cart'ı ile otomatik merge olmalı.

**Decision:**
- `Cart` aggregate `user_id` VEYA `guest_session_id` ile identifiable (XOR — DB chk_carts_owner CHECK + entity validateOwnership)
- Guest session UUID v4, httpOnly + Secure + SameSite=Strict cookie'de saklanır (`guest_cart_token`, 30 gün TTL)
- DB partial unique index'ler tek aktif default garantiler:
  - `uniq_carts_user_active` (WHERE user_id IS NOT NULL AND status='active')
  - `uniq_carts_guest_active` (WHERE guest_session_id IS NOT NULL AND status='active')
- Login/Register handler'ları `MergeGuestCartCommand` tetikler (try/catch — auth fail olmaz):
  - Guest cart yok → no-op
  - User cart yok → `transferOwnership` (en hafif yol)
  - İkisi de var → `CartMerger.applyToUserCart` (quantity sum, OLDEST snapshot price korunur), guest cart abandoned
- Checkout (PlaceOrder) ZORUNLU auth — guest order yok (KVKK denetim)
- Guest cart 30 gün sonra `sales:clean-guest-carts` artisan ile otomatik silinir
- BFF (Next.js) `guest_cart_token` cookie'sini browser ↔ Laravel arasında manuel forward eder (cross-origin)

**Rationale:**
- Konversiyon iyileşir (login friction yok)
- Snapshot pricing zaten cart_items'ta var (oldest snapshot kuralı kullanıcıyı fiyat artışından korur)
- KVKK: guest_session_id anonim (kişisel veri değil), user_id'ye merge edildiği an audit kaydedilir (`sales.cart_transferred_from_guest`)
- Cookie güvenliği: 3 layer (HttpOnly + Secure + SameSite=Strict) XSS/CSRF/cookie-jacking koruması

**Consequences:**
- ✅ E-ticaret konversiyon iyileşir, modern UX
- ✅ Guest browsing analytics yapılabilir (anonim)
- ✅ Cart merge kuralı user adına matematiksel olarak adil (oldest snapshot)
- ❌ Cleanup job zorunlu (storage flood koruması)
- ❌ Merge logic karmaşık — full feature test coverage şart
- ❌ Bot saldırı vektörü (cart spam) → rate-limit zorunlu (Code Review Fix #2 — `cart-write` 30/dakika throttle)
- ❌ BFF cookie mediation kompleksitesi (Browser ↔ Next ↔ Laravel cross-origin)

**Alternatives Considered:**
- LocalStorage'da client-side cart → server-side state ile uyumsuz, snapshot zor, server-rendered fiyat doğrulaması yok
- IP bazlı identification → CGNAT, mobil internet ile karışır, multi-tab confusion
- Anonymous user account → DB şişirir (her ziyaretçi user row), admin user yönetimini bozar

---

## ADR-018: Address Management with Snapshot Pattern

**Status:** Accepted
**Date:** 2026-05-07

**Context:** E-ticaret sistemde sipariş için adres bilgisi şart. Kullanıcı adresleri zamanla değişebilir veya silinebilir, ama eski siparişlerin adres bilgisi korunmalı (KVKK + audit + müşteri hizmetleri için "1 yıl önce hangi adrese gönderdik" sorusu). Türkiye için il/ilçe yapısı statik veri, runtime API bağımlılığı istemiyoruz.

**Decision:**
- Yeni modül: `Address` (Identity bounded context altında — User aggregate'e ait ama ayrı lifecycle)
- Bir kullanıcının N (uygulama tarafında 10) aktif adresi olabilir (soft-delete'ler sayılmaz)
- 1 default delivery + 1 default billing flag (aynı adres olabilir); DB partial unique index'leri tek default garantiler
- Sipariş anında adres JSONB olarak snapshot'lanır (`orders.delivery_address`, `orders.billing_address`); FK referansı (`*_address_id`) nullable
- Adres soft delete edilir (FK ON DELETE SET NULL — snapshot her hâlükârda korur)
- Default re-pick policy: silinen adres default ise en son aktif adres otomatik default'a promote (DeleteAddressHandler)
- Türkiye için il/ilçe statik liste — `backend/resources/data/turkey-locations.json` (81 il, 973 ilçe; `turkiyeapi.dev` kaynaklı, build sırasında transform)
- `TurkeyLocationsService` singleton — runtime parse, case-insensitive lookup
- IDOR koruması: tüm Address endpoint'leri `findByIdAndUser` üzerinden; cross-user erişim 404 ADDRESS_NOT_FOUND (anti-enumeration)
- Phone E.164 normalize (`+905551234567`); display formatı `0 555 123 45 67`
- PlaceOrderHandler `deliveryAddressId` zorunlu, `billingAddressId` null verilirse delivery ile aynı

**Rationale:**
- Snapshot pattern → adres değişse de eski sipariş bozulmaz; KVKK + muhasebe için zorunlu
- JSONB → flexible, query'lenebilir (`idx_orders_delivery_city`), esnek schema
- Soft delete → audit + history için referansı koru ama listings'ten gizle
- Statik il/ilçe → external API'ye bağımlılık yok, hızlı (<10ms warm cache), offline çalışır
- Default address → checkout UX iyileşir (otomatik seçim)
- E.164 phone format → uluslararası standart, downstream (SMS gateway) uyumu

**Consequences:**
- ✅ Audit trail tam (KVKK uyumlu)
- ✅ Sipariş history immutable (snapshot pattern)
- ✅ UX rahat (default seçim + auto re-pick)
- ✅ TR locations build-time embed; runtime API/DB call yok
- ❌ Storage maliyeti: JSONB her order'da (~500 byte snapshot)
- ❌ İl/ilçe data güncellemesi manuel (yeni mahalle/ilçe — annually rebuild + JSON regenerate)
- ❌ Türkiye dışı için refactor gerekir (`countryCode` enum + per-country location service)
- ❌ Address title strip_tags eksik (Code Review Fix backlog) — XSS defense-in-depth eksiği

**Alternatives Considered:**
- FK ile bağlama (snapshot yok) → adres silince order bozulur; soft delete edilse bile gereksiz JOIN + snapshot'ın yokluğu KVKK risk
- Sadece string adres (tek kolon) → sorgulanabilirlik 0, analytics yapılamaz
- External address API (örn Google Places) → 10 günlük scope için overkill, runtime bağımlılığı, latency, KVKK üçüncü taraf risk
- TR-only enum (DB CHECK) → eklenen yeni il/ilçe migration gerektirir; JSON daha esnek

---

## 📌 KARAR EKLEMEK

Yeni bir mimari karar verildiğinde:

1. ADR numarasını al (mevcut max + 1)
2. Format'a uy
3. **Status: Accepted** ile başla
4. CLAUDE.md'nin "Doküman İndeksi" bölümüne (gerekirse) referans ekle
5. Commit message: `docs: ADR-XXX {kısa başlık}`

Eski karar değişirse:
1. Eski ADR'nin Status'ünü "Superseded by ADR-YYY" yap
2. Yeni ADR'yi ekle, "Supersedes ADR-XXX" not düş

---

**Last Updated:** 2026-05-07
**Toplam ADR:** 18
