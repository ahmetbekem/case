# Frontend Kullanım Rehberi

Dehasoft test projesi frontend (Next.js 16 + React 19 + FSD) için sayfa-route-endpoint
haritası ve kullanıcı akışları.

> **Canlı versiyon:** http://localhost:3030/site-map (bu doc'la birebir senkron — sayfa
> kart'lı görsel rehber)

---

## 1. Sayfa Haritası

Aşağıdaki tablo `frontend/src/app/` altında **gerçekten var olan** sayfaları listeler.
Doc strategy'sinde planlanmış ama henüz implement edilmemiş sayfalar `❌ YOK` ile
ayrıca işaretlenmiştir.

### Mevcut Sayfalar (10 page)

| Route | Dosya | Auth | Rol | Backend Endpoint(ler)i | Açıklama |
|-------|-------|------|-----|------------------------|----------|
| `/` | `app/page.tsx` | Public | — | (yok) | **⚠️ Next.js scaffold placeholder** — gerçek anasayfa henüz yazılmadı |
| `/login` | `app/(auth)/login/page.tsx` | Public | — | `POST /api/auth/login` | Giriş formu (email + password) |
| `/register` | `app/(auth)/register/page.tsx` | Public | — | `POST /api/auth/register` | Yeni hesap kaydı |
| `/products` | `app/(shop)/products/page.tsx` | Public | — | `GET /api/catalog/products` | Ürün listesi (paginated, search, currency switch) |
| `/products/[id]` | `app/(shop)/products/[id]/page.tsx` | Public | — | `GET /api/catalog/products/{id}` | Ürün detay + sepete ekle |
| `/cart` | `app/(shop)/cart/page.tsx` | Required | customer | `GET /api/sales/cart`, `PATCH /api/sales/cart/items/{id}`, `DELETE /api/sales/cart/items/{id}`, `DELETE /api/sales/cart` | Sepet — inline quantity update + remove + clear |
| `/checkout` | `app/(shop)/checkout/page.tsx` | Required | customer | `POST /api/sales/orders` | Display currency seç + notes + sipariş onayla |
| `/orders` | `app/(shop)/orders/page.tsx` | Required | customer / admin | `GET /api/sales/orders` | Siparişlerim — admin için `?adminScope=1` toggle |
| `/orders/[id]` | `app/(shop)/orders/[id]/page.tsx` | Required | customer / admin | `GET /api/sales/orders/{id}`, `POST /api/sales/orders/{id}/cancel` | Sipariş detay + iptal formu (pending guard) |
| `/admin/products` | `app/(admin)/admin/products/page.tsx` | Required | admin | `GET /api/catalog/products`, `POST /api/catalog/products`, `DELETE /api/catalog/products/{id}`, `POST /api/catalog/products/{id}/stock` | Inline create + delete + stock +/- adjust |

### Henüz Implement Edilmemiş (Strategy'de Planlandı, Yok)

| Route | Backend Hazır mı? | Notlar |
|-------|---|--------|
| `/profile` | ✅ `GET /api/auth/me` var | Frontend sayfası ❌ YOK — mevcut user `Header.UserMenu`'da görünüyor |
| `/admin` (dashboard) | ❌ `/admin/sales/stats` endpoint'i de yok | Hem backend hem frontend ❌ YOK |
| `/admin/products/new`, `/admin/products/[id]` | ✅ POST/PATCH var | Ayrı sayfa yerine `/admin/products` inline form kullanıyor |
| `/admin/orders`, `/admin/orders/[id]` | ✅ `/sales/orders` admin scope ile | Ayrı admin route ❌ YOK — `/orders?adminScope=1` aynı işi yapıyor |
| `/admin/users`, `/admin/users/[id]` | ❌ Backend admin user CRUD yok | İki taraf da ❌ YOK |
| `/admin/currency` | ❌ Backend admin currency yok | İki taraf da ❌ YOK |

---

## 2. Proxy Route Haritası

`frontend/src/app/api/` — tarayıcıdan gelen tüm `/api/*` istekleri, BFF (Backend-for-Frontend)
proxy katmanından geçer. `BACKEND_PROXY_SECRET` header server-side eklenir, asla browser'a
sızmaz (`scripts/check-backend-leak.sh` her CI run'ında doğrular).

| Frontend Path | Backend Target | Method | Açıklama |
|---------------|---------------|--------|----------|
| `/api/auth/register` | `POST /api/v1/auth/register` | POST | Body forward + cookie set (st_at, st_rt) |
| `/api/auth/login` | `POST /api/v1/auth/login` | POST | Body forward + cookie set |
| `/api/auth/refresh` | `POST /api/v1/auth/refresh` | POST | Cookie'den st_rt alır, response cookie'ye yazar |
| `/api/auth/logout` | `POST /api/v1/auth/logout` | POST | Cookie clear |
| `/api/auth/me` | `GET /api/v1/auth/me` | GET | Bearer header inject + 401'de tek seferlik refresh retry |
| `/api/[...path]` | `* /api/v1/{path}` | * | **Catch-all** — diğer tüm endpoint'ler buradan geçer (catalog, sales, currency) |

**Catch-all özellikleri** (`src/app/api/[...path]/route.ts`):
- 401 alınırsa otomatik `tryRefresh()` → original request retry (kullanıcı 1 kere refresh süresince hiçbir şey hissetmez)
- `X-Proxy-Secret` header'ı `serverEnv.BACKEND_PROXY_SECRET` ile imzalanır
- `Origin` header'ı `Origin: http://backend` olarak set edilir (proxy guard için)
- Cookie forwarding st_at + st_rt

---

## 3. Kullanıcı Akışları (User Journeys)

### Akış 1: Yeni kullanıcı kayıt + sipariş

```
/register           → email + password + fullName form
POST /api/auth/register → 201, st_at + st_rt cookie set
Auto-redirect → /products

/products           → listeden ürün seç
/products/[id]      → "Sepete Ekle" tıkla
                     POST /api/sales/cart/items
                     (kullanıcı login değilse hata cevabı görünür — login yönlendirmesi YOK)

/cart               → quantity ayarla, "Ödeme Yap" tıkla
/checkout           → display currency seç (TRY/USD/EUR) + notes
                     POST /api/sales/orders → 201
Auto-redirect → /orders/[new-id]

/orders             → tüm siparişler listesi
/orders/[id]        → detay + (pending ise) iptal et
```

> **⚠️ Not:** Mevcut kodda **returnUrl mantığı yok**. Login sonrası
> her zaman `/products`'a yönlenir (`useLoginMutation(redirectTo='/products')`).
> Sepete ekle gibi auth-required action'larda 401 dönerse error mesajı
> gösterilir; otomatik login redirect implement edilmedi.

### Akış 2: Admin ürün yönetimi

```
/login              → admin user
Auto-redirect → /products  (default redirect — /admin'e gitmiyor)

Header'da "Admin" linki görünür  (user.role === 'admin' kontrolü)
Tıkla → /admin/products

/admin/products     → admin guard sayfa içinde (useCurrentUser + role check)
                     Customer geldiyse "Bu sayfaya erişim için admin yetkisi gerekli"
                     Admin için: form + liste + stock +/- + sil
                     - POST /api/catalog/products    (yeni)
                     - POST /api/catalog/products/{id}/stock  (delta)
                     - DELETE /api/catalog/products/{id}
                     Tüm mutation'lar TanStack Query invalidation
```

### Akış 3: Admin sipariş takibi

```
Admin için /admin/orders sayfası YOK — bunun yerine:

/orders?adminScope=1 → admin tüm siparişleri görür
                       (OrdersPage.tsx içinde isAdmin ise toggle gösterir)
                       GET /api/sales/orders?adminScope=1

/orders/[id]         → admin başkasının siparişine bakabilir
                       (backend assertCanBeViewedBy → admin için her zaman izinli)

İptal → POST /api/sales/orders/{id}/cancel  (admin de aynı endpoint'i kullanır)
```

> **⚠️ Eksik:** Sipariş "tamamla" (pending → completed) butonu YOK; backend
> tarafında da `complete` endpoint'i implement edilmemiş.

### Akış 4: Token refresh (otomatik, kullanıcı görmez)

```
Kullanıcı /products sayfasında
Access token TTL doluyor (default 15 dakika)
Bir sonraki API çağrısı 401 döner

Catch-all proxy (src/app/api/[...path]/route.ts):
  1. 401 yakala
  2. tryRefresh() → POST /api/v1/auth/refresh (cookie'den st_rt al)
  3. Yeni st_at + st_rt response cookie'lerine yaz (rotation)
  4. Original request'i yeni token ile tekrarla
  5. Cevabı kullanıcıya döndür

Kullanıcı tek bir titreme bile görmez. Refresh fail olursa (replay
detection veya expired) — orijinal 401 cevabı geri döner; UI o
noktada login'e yönlendirme yapmıyor (manuel /login'e gitmek gerek).
```

---

## 4. Auth Guard Stratejisi

**`middleware.ts` dosyası YOK** — Next.js Edge Middleware kullanılmıyor. Auth gating
**page-level**, üç farklı pattern ile:

| Sayfa | Pattern | Dosya |
|-------|---------|-------|
| `/cart` | `useCart()` çağırır; 401 → "Yükleniyor…" + boş cevap | `pages-ui/cart/ui/CartPage.tsx` |
| `/checkout` | `useCart()` boşsa "sepetiniz boş" + login'e direkt yönlendirme yok | `pages-ui/checkout/ui/CheckoutPage.tsx` |
| `/orders` | `useOrderList()` 401 hatasında "Yükleniyor…" döner; admin için `adminScope` toggle | `pages-ui/orders/ui/OrdersPage.tsx` |
| `/orders/[id]` | `useOrder(id)` — 404 ise "Sipariş bulunamadı" (IDOR pattern) | `pages-ui/order-detail/ui/OrderDetailPage.tsx` |
| `/admin/products` | **Açık role check** — `user.role !== 'admin'` ise mesaj | `pages-ui/admin-products/ui/AdminProductsPage.tsx` |

**Login sonrası redirect:**
- `useLoginMutation(redirectTo = '/products')` — sabit, returnUrl yok
- Header'daki "Admin" linki sadece `user.role === 'admin'` olduğunda render edilir
  (`widgets/header/Header.tsx`)

**Cookies:**
- `st_at` (access token, httpOnly + secure + sameSite=lax) — 15 dk
- `st_rt` (refresh token, aynı flag'ler) — 14 gün

---

## 5. API Çağrı Pattern'leri

> **🔴 Kural:** Tarayıcı **asla** doğrudan backend (`localhost:8000`) çağıramaz.
> ESLint rule (`no-restricted-imports`) + leak gate script bunu garanti eder.
> Tüm istekler `/api/*` path'inden geçer.

### Server Component (RSC) — `server-fetch.ts`

```ts
// frontend/src/shared/api/server-fetch.ts kullanımı
import 'server-only';
import { backendJson } from '@/shared/api/server-fetch';

export default async function ProductsPage() {
  const products = await backendJson<ApiResponse<Product[]>>(
    '/catalog/products',
    { cache: 'no-store' },
  );
  return <ProductList items={products.data} />;
}
```

`backendJson` otomatik olarak `LARAVEL_API_URL` + `BACKEND_PROXY_SECRET` + `Origin` header'larını ekler.

### Client Component — `clientFetch`

```tsx
'use client';
import { clientFetch } from '@/shared/api/client';

export function AddToCartButton({ productId }: Props) {
  const handleClick = async () => {
    await clientFetch('/api/sales/cart/items', {
      method: 'POST',
      body: { productId, quantity: 1 },
    });
  };
  return <Button onClick={handleClick}>Sepete Ekle</Button>;
}
```

`clientFetch` same-origin `/api/*` proxy'ye gider, BFF cookie + secret ekler.

### TanStack Query Hook (mutation + cache)

```tsx
// features/cart-add-item/api/addItemMutation.ts pattern'i
export function useAddCartItemMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { productId: string; quantity: number }) =>
      clientFetch('/api/sales/cart/items', { method: 'POST', body: input }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY }),
  });
}
```

---

## 6. State Management

| Tip | Lib | Kullanım Yeri |
|-----|-----|---------------|
| **Server state** | TanStack Query 5 | API cevapları (cart, orders, products, currency rates, /me) |
| **Auth user (synchronous mirror)** | Zustand 5 | `entities/user/model/userStore.ts` — Header sync read için |
| **Currency selection** | Zustand 5 + `persist` | `entities/currency/model/currencyStore.ts` — localStorage |
| **Form state** | React Hook Form 7 + Zod 4 | `features/auth-login/model/useLoginForm.ts`, vs |
| **Cookies** | Server-only — `app/api/_lib/cookies.ts` | st_at + st_rt (httpOnly), browser asla okuyamaz |

**Source of truth:** Auth için **TanStack Query**'nin `/me` cache'i kanonik; `useUserStore` sadece widget'ların subscription olmadan okuyabilmesi için ayna.

---

## 7. Layout Hiyerarşisi

```
src/app/
├── layout.tsx                   # Root: HTML, Geist fonts, QueryProvider
├── page.tsx                     # / (⚠️ Next.js scaffold placeholder)
├── globals.css
│
├── (auth)/                      # Route group — auth sayfaları
│   ├── login/page.tsx
│   └── register/page.tsx        # → minimal layout (header yok)
│
├── (shop)/                      # Route group — shopping flow
│   ├── layout.tsx               # Header (Brand + currency switch + cart count + UserMenu)
│   ├── cart/page.tsx
│   ├── checkout/page.tsx
│   ├── orders/page.tsx
│   ├── orders/[id]/page.tsx
│   ├── products/page.tsx
│   └── products/[id]/page.tsx
│
├── (admin)/                     # Route group — admin
│   └── admin/
│       └── products/page.tsx    # Header'ı sayfa içinde manuel mount eder
│                                # (admin için ayrı layout YOK)
│
└── api/                         # BFF proxy
    ├── _lib/                    # cookies.ts, refresh.ts, errors.ts (helpers)
    ├── [...path]/route.ts       # Catch-all
    └── auth/{login,logout,refresh,register,me}/route.ts
```

> **⚠️ İsim çakışması yok:** `(auth)` ve `(admin)` parantez içi route group'lardır,
> URL'de görünmez. Yani `/login` (root'tan), `/cart` (root'tan), `/admin/products`
> (root'tan).

---

## 8. Geliştirici Notları

### Component Mimarisi (FSD)

```
shared      → primitives (Button/Input/Label/Card/cn helper) + api clients
entities    → domain'den gelen kayıtlar (user, product, cart, order, currency)
features    → kullanıcı action'ları (login, register, add-to-cart, place-order, ...)
widgets     → birden fazla feature'ı birleştiren UI (Header)
pages-ui    → sayfa görünümü (Header dışı içerik)
app/        → Next.js routing katmanı (sadece pages-ui'yi mount eder)
```

**Dependency rule:** `app → pages-ui → widgets → features → entities → shared`. Aşağı katmana çıkarmak yasak.

### shadcn/ui

Tam shadcn init yapılmadı — `cn.ts` helper + manual `Button`, `Input`, `Label`, `Card` primitives `shared/ui/` altında. Variant'lar `class-variance-authority` ile.

### Error Handling

- `error.tsx` / `not-found.tsx` Next.js dosyaları henüz **yazılmadı** (default fallback'ler aktif)
- API hataları `ClientApiError` instance olarak fırlatılır (`shared/api/client.ts`); `error instanceof ClientApiError` ile message okunur
- Inline error UI'ları (`<p className="bg-red-50 text-red-700">…</p>`) sayfa içlerinde

### Loading States

`loading.tsx` Next.js Suspense fallback'leri **yazılmadı**. Her sayfa kendi `isLoading` state'ini inline render ediyor (`<p className="text-sm text-slate-500">Yükleniyor…</p>`).

### Form Validation

- Zod schema'lar feature içinde: `features/auth-login/model/useLoginForm.ts`
- React Hook Form `useForm({ resolver: zodResolver(schema) })` ile bağlı
- Server-side hata cevaplarını client-side hatayla birleştiren mekanizma yok — server hatası ayrı `<p>` içinde gösterilir

---

## 9. Test Erişim Bilgileri

### Test Hesapları

> **⚠️ Seeder eksik:** `backend/database/seeders/DatabaseSeeder.php` Laravel default'u
> tutuyor (`App\Models\User`'ı seed etmeye çalışıyor — bu proje `Domain\Identity\Entities\User`
> kullanıyor). Çalıştırılırsa fail eder. **Demo admin/customer hesabı YOK.**
>
> Manuel oluşturmak için: `/register` ile yeni hesap aç, ardından PostgreSQL'de
> rolü güncelle:
> ```bash
> docker exec dehasoft-postgres psql -U dehasoft -d testproject \
>   -c "UPDATE identity.users SET role = 'admin' WHERE email = 'youremail@x.com';"
> ```

### URL'ler (sistem ayaktayken)

| Servis | URL | Notlar |
|---|---|---|
| Frontend | http://localhost:3030 | (port `3000` çakışmasından `3030`) |
| Site Map | http://localhost:3030/site-map | Bu doc'un canlı görsel versiyonu |
| Backend API | http://localhost:8000/api/v1 | Sadece dev, BFF proxy üzerinden gitmek tercih |
| Swagger UI | http://localhost:8000/api/documentation | Try-it-out + Bearer auth |
| OpenAPI JSON | http://localhost:8000/docs | Postman'a import için |
| Kibana | http://localhost:5601 | Index pattern: `logs-*` |
| RabbitMQ UI | http://localhost:15672 | admin / admin |

---

## 10. Bilinen Eksikler / İlerideki İşler

| Eksik | Etki | Tahmini iş |
|-------|------|-----------|
| Anasayfa (`/`) — Next.js scaffold | Demoda kötü görünür | 1-2 saat |
| `error.tsx` / `not-found.tsx` / `loading.tsx` | Hata cevabı default Next.js fallback | 1 saat |
| Login `returnUrl` | Auth-required action'lardan login'e yönlenip geri dönmek imkansız | 2-3 saat |
| `/profile` sayfası | User kendi bilgilerini güncelleyemiyor | 2 saat |
| Admin dashboard (`/admin`) | Admin'e ilk açılış sayfası yok | 2-3 saat (backend stats endpoint da yok — +backend) |
| Seeder | Test hesapları manuel açılmak zorunda | 30 dk |
| `/admin/orders` ayrı route | Admin'in tüm siparişleri görme deneyimi `?adminScope=1` toggle ile karışık | 2 saat |
| `/admin/users` + backend admin user CRUD | Kullanıcı pasifleştirme/aktif yok | 1 gün (backend + frontend) |
