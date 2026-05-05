/**
 * Tek kaynak — docs/FRONTEND_GUIDE.md §1 ile birebir senkron olmak zorunda.
 * Yeni sayfa eklendiğinde HEM doc HEM bu liste güncellensin.
 */
export type Access = 'public' | 'customer' | 'admin';

export interface RouteEntry {
  path: string;
  title: string;
  description: string;
  access: Access;
  endpoints: string[];
  external?: boolean;
  /** true ise kart "Coming Soon" disabled görünür */
  comingSoon?: boolean;
  /** Sayfa-bazlı not (ör. "Next.js scaffold placeholder") */
  badge?: string;
}

export interface RouteSection {
  section: string;
  description: string;
  routes: RouteEntry[];
}

export const SECTIONS: ReadonlyArray<RouteSection> = [
  {
    section: 'Public',
    description: 'Auth gerekmeyen sayfalar — herkes görür.',
    routes: [
      {
        path: '/',
        title: 'Anasayfa',
        description: 'Şu an Next.js scaffold placeholder — gerçek anasayfa henüz yazılmadı.',
        access: 'public',
        endpoints: [],
        badge: 'placeholder',
      },
      {
        path: '/login',
        title: 'Giriş',
        description: 'Email + şifre ile giriş; cookie set edilir, /products\'a yönlenir.',
        access: 'public',
        endpoints: ['POST /api/auth/login'],
      },
      {
        path: '/register',
        title: 'Kayıt',
        description: 'Yeni customer hesabı; başarıyla kayıt → otomatik giriş.',
        access: 'public',
        endpoints: ['POST /api/auth/register'],
      },
      {
        path: '/products',
        title: 'Ürün Listesi',
        description: 'Sayfalanmış aktif ürünler, currency switcher ile fiyat çevirisi.',
        access: 'public',
        endpoints: ['GET /api/catalog/products'],
      },
      {
        path: '/products/sample',
        title: 'Ürün Detay',
        description: 'Tek ürün + sepete ekle. URL pattern: /products/[id].',
        access: 'public',
        endpoints: ['GET /api/catalog/products/{id}'],
      },
      {
        path: '/site-map',
        title: 'Site Haritası',
        description: 'Bu sayfa — tüm route + endpoint rehberi.',
        access: 'public',
        endpoints: [],
      },
    ],
  },
  {
    section: 'Customer (Auth Required)',
    description:
      'Giriş yapmış müşteriler için. Auth yoksa boş cevap görüntülenir; otomatik login yönlendirmesi YOK (returnUrl mantığı henüz implement edilmedi).',
    routes: [
      {
        path: '/cart',
        title: 'Sepetim',
        description: 'Quantity update, kalem sil, sepeti boşalt.',
        access: 'customer',
        endpoints: [
          'GET /api/sales/cart',
          'PATCH /api/sales/cart/items/{id}',
          'DELETE /api/sales/cart/items/{id}',
          'DELETE /api/sales/cart',
        ],
      },
      {
        path: '/checkout',
        title: 'Ödeme',
        description: 'Display currency seç + notes + sipariş ver. Boş sepette uyarı verir.',
        access: 'customer',
        endpoints: ['POST /api/sales/orders'],
      },
      {
        path: '/orders',
        title: 'Siparişlerim',
        description:
          'Kullanıcı kendi siparişleri. Admin ise ?adminScope=1 toggle ile tümünü görür.',
        access: 'customer',
        endpoints: ['GET /api/sales/orders'],
      },
      {
        path: '/orders/sample',
        title: 'Sipariş Detay',
        description:
          'Kalemler + display currency + iptal formu (pending guard). URL: /orders/[id].',
        access: 'customer',
        endpoints: ['GET /api/sales/orders/{id}', 'POST /api/sales/orders/{id}/cancel'],
      },
      {
        path: '/profile',
        title: 'Profilim',
        description: 'Backend /auth/me var ama frontend sayfası henüz yazılmadı.',
        access: 'customer',
        endpoints: ['GET /api/auth/me'],
        comingSoon: true,
      },
    ],
  },
  {
    section: 'Admin (Role: admin)',
    description:
      'admin rolü gerekli. Sayfa içinde useCurrentUser + role check; customer geldiyse "yetki yok" mesajı.',
    routes: [
      {
        path: '/admin/products',
        title: 'Ürün Yönetimi',
        description: 'Inline create + delete + stock +/- adjust.',
        access: 'admin',
        endpoints: [
          'GET /api/catalog/products',
          'POST /api/catalog/products',
          'DELETE /api/catalog/products/{id}',
          'POST /api/catalog/products/{id}/stock',
        ],
      },
      {
        path: '/admin',
        title: 'Admin Dashboard',
        description: 'İstatistik + bekleyen sipariş widget. Backend stats endpoint\'i de yok.',
        access: 'admin',
        endpoints: [],
        comingSoon: true,
      },
      {
        path: '/admin/orders',
        title: 'Tüm Siparişler',
        description:
          'Şu an /orders?adminScope=1 ile aynı işi yapıyor; ayrı admin route yazılmadı.',
        access: 'admin',
        endpoints: [],
        comingSoon: true,
      },
      {
        path: '/admin/users',
        title: 'Kullanıcı Yönetimi',
        description: 'Backend admin user CRUD da implement edilmedi.',
        access: 'admin',
        endpoints: [],
        comingSoon: true,
      },
      {
        path: '/admin/currency',
        title: 'Kur Yönetimi',
        description: 'Backend admin currency endpoint\'leri de yok.',
        access: 'admin',
        endpoints: [],
        comingSoon: true,
      },
    ],
  },
  {
    section: 'System / External',
    description: 'Diğer servisler — yeni sekmede açılır.',
    routes: [
      {
        path: 'http://localhost:8000/api/documentation',
        title: 'Swagger UI',
        description: '23 endpoint + 27 schema, Bearer/Cookie auth ile try-it-out.',
        access: 'public',
        endpoints: [],
        external: true,
      },
      {
        path: 'http://localhost:8000/docs',
        title: 'OpenAPI 3.0 JSON',
        description: 'Postman\'a import için ham spec.',
        access: 'public',
        endpoints: [],
        external: true,
      },
      {
        path: 'http://localhost:5601',
        title: 'Kibana — Logs',
        description: 'Index pattern: logs-* (audit/app/access).',
        access: 'admin',
        endpoints: [],
        external: true,
      },
      {
        path: 'http://localhost:15672',
        title: 'RabbitMQ Management',
        description: 'Queue + exchange + DLQ inspection. admin/admin.',
        access: 'admin',
        endpoints: [],
        external: true,
      },
    ],
  },
];
