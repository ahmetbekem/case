'use client';

import {
  ArrowRight,
  BookOpen,
  Boxes,
  ClipboardList,
  ExternalLink,
  LayoutDashboard,
  Map,
  ShoppingBag,
  ShoppingCart,
  UserPlus,
} from 'lucide-react';
import Link from 'next/link';
import type { ComponentType, SVGProps } from 'react';

import { useCurrentUser } from '@/entities/user';
import { cn } from '@/shared/lib/cn';
import { Button } from '@/shared/ui';

interface QuickLink {
  href: string;
  label: string;
  description: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  cta?: string;
  external?: boolean;
  /** true ise login yokken kart opaque + "giriş gerek" notu gösterir */
  requiresAuth?: boolean;
  /** true ise sadece admin görsün; diğerlerine hiç render edilmez */
  adminOnly?: boolean;
}

const PUBLIC_LINKS: ReadonlyArray<QuickLink> = [
  {
    href: '/products',
    label: 'Ürünleri İncele',
    description: 'Aktif ürün listesi, sayfalama, currency switcher ile fiyat çevirisi.',
    icon: ShoppingBag,
  },
  {
    href: '/site-map',
    label: 'Site Haritası',
    description: 'Tüm sayfaların ve API endpoint\'lerinin canlı rehberi.',
    icon: Map,
  },
  {
    href: '/register',
    label: 'Hesap Aç',
    description: 'Sepet ve sipariş işlemleri için hızlıca kayıt ol.',
    icon: UserPlus,
  },
];

const CUSTOMER_LINKS: ReadonlyArray<QuickLink> = [
  {
    href: '/cart',
    label: 'Sepetim',
    description: 'Sepetteki ürünleri yönet, miktar değiştir, ödemeye geç.',
    icon: ShoppingCart,
    requiresAuth: true,
  },
  {
    href: '/orders',
    label: 'Siparişlerim',
    description: 'Geçmiş siparişler + iptal (pending statüsündekiler için).',
    icon: ClipboardList,
    requiresAuth: true,
  },
];

const ADMIN_LINKS: ReadonlyArray<QuickLink> = [
  {
    href: '/admin/products',
    label: 'Ürün Yönetimi',
    description: 'Inline create + delete + stock +/- adjust.',
    icon: LayoutDashboard,
    adminOnly: true,
  },
];

const EXTERNAL_LINKS: ReadonlyArray<QuickLink> = [
  {
    href: 'http://localhost:8000/api/documentation',
    label: 'API Dokümantasyonu',
    description: 'Swagger UI ile 23 endpoint try-it-out + Bearer auth.',
    icon: BookOpen,
    cta: 'Aç',
    external: true,
  },
];

function ActionCard({
  link,
  reachable,
}: {
  link: QuickLink;
  reachable: boolean;
}) {
  const Icon = link.icon;
  const baseClass = cn(
    'group flex h-full flex-col rounded-lg border border-slate-200 bg-white p-5 transition',
    reachable && 'hover:border-slate-400 hover:shadow-sm',
    !reachable && 'opacity-60',
  );

  const body = (
    <>
      <div className="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-md bg-slate-900 text-white">
        <Icon className="h-5 w-5" aria-hidden />
      </div>
      <h3 className="text-base font-semibold text-slate-900">{link.label}</h3>
      <p className="mt-1 flex-1 text-sm text-slate-600">{link.description}</p>
      <div className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-slate-900">
        {link.cta ?? 'Git'}
        {link.external ? (
          <ExternalLink className="h-4 w-4" aria-hidden />
        ) : (
          <ArrowRight
            className="h-4 w-4 transition group-hover:translate-x-0.5"
            aria-hidden
          />
        )}
      </div>
      {!reachable && (
        <p className="mt-2 text-xs text-amber-700">Erişim için önce giriş yap.</p>
      )}
    </>
  );

  if (link.external) {
    return (
      <a
        href={link.href}
        target="_blank"
        rel="noopener noreferrer"
        className={baseClass}
      >
        {body}
      </a>
    );
  }

  if (!reachable) {
    return <div className={baseClass}>{body}</div>;
  }

  return (
    <Link href={link.href} className={baseClass}>
      {body}
    </Link>
  );
}

export function HomePage() {
  const { data: user } = useCurrentUser();
  const isLoggedIn = user !== null && user !== undefined;
  const isAdmin = user?.role === 'admin';

  // Customer kartları her zaman görünür (auth yokken opaque)
  // Admin kartları yalnızca admin'e gösterilir; diğerleri için /site-map yönlendirmesi var
  const adminLinks = isAdmin ? ADMIN_LINKS : [];

  return (
    <div className="mx-auto max-w-6xl space-y-12 px-4 py-10">
      <section className="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-8 sm:p-12">
        <div className="max-w-3xl space-y-4">
          <span className="inline-flex items-center rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white">
            E-Ticaret Demo Projesi
          </span>
          <h1 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
            E-ticaret demo — auth, sepet, sipariş, multi-currency.
          </h1>
          <p className="text-base text-slate-600 sm:text-lg">
            Laravel 11 (Onion + CQRS + DDD-Lite) backend, Next.js 16 (FSD + BFF
            proxy) frontend, RabbitMQ → Elasticsearch decoupled logging.
            {isLoggedIn ? (
              <>
                {' '}
                Hoş geldin <strong>{user.fullName}</strong>.
              </>
            ) : (
              <>
                {' '}
                Sayfalara göz atmaya başla veya hesap aç.
              </>
            )}
          </p>
          <div className="flex flex-wrap items-center gap-3 pt-2">
            {!isLoggedIn ? (
              <>
                <Link href="/login">
                  <Button>Giriş Yap</Button>
                </Link>
                <Link href="/register">
                  <Button variant="outline">Hesap Aç</Button>
                </Link>
              </>
            ) : (
              <Link href="/products">
                <Button>Alışverişe Başla</Button>
              </Link>
            )}
            <Link href="/site-map">
              <Button variant="ghost">
                Site Haritası
                <ArrowRight className="ml-1 h-4 w-4" />
              </Button>
            </Link>
          </div>
        </div>
      </section>

      <section className="space-y-4">
        <header>
          <h2 className="text-xl font-semibold text-slate-900">Sayfalar</h2>
          <p className="text-sm text-slate-500">
            Açık erişimli sayfalar — auth gerekmez.
          </p>
        </header>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {PUBLIC_LINKS.map((link) => (
            <ActionCard key={link.href} link={link} reachable />
          ))}
        </div>
      </section>

      <section className="space-y-4">
        <header>
          <h2 className="text-xl font-semibold text-slate-900">Hesabım</h2>
          <p className="text-sm text-slate-500">
            Sepet ve sipariş işlemleri — giriş yapman gerekiyor.
          </p>
        </header>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {CUSTOMER_LINKS.map((link) => (
            <ActionCard
              key={link.href}
              link={link}
              reachable={isLoggedIn}
            />
          ))}
        </div>
      </section>

      {isAdmin && (
        <section className="space-y-4">
          <header>
            <h2 className="text-xl font-semibold text-slate-900">Yönetim</h2>
            <p className="text-sm text-slate-500">
              Admin yetkisi gerektiren sayfalar.
            </p>
          </header>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {adminLinks.map((link) => (
              <ActionCard key={link.href} link={link} reachable />
            ))}
          </div>
        </section>
      )}

      <section className="space-y-4">
        <header>
          <h2 className="text-xl font-semibold text-slate-900">
            Geliştirici Araçları
          </h2>
          <p className="text-sm text-slate-500">
            Backend dokümantasyon ve gözlem servisleri.
          </p>
        </header>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {EXTERNAL_LINKS.map((link) => (
            <ActionCard key={link.href} link={link} reachable />
          ))}
          <Link
            href="/site-map"
            className="group flex h-full flex-col rounded-lg border border-dashed border-slate-300 bg-white p-5 transition hover:border-slate-400"
          >
            <div className="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-md bg-slate-200 text-slate-700">
              <Boxes className="h-5 w-5" aria-hidden />
            </div>
            <h3 className="text-base font-semibold text-slate-900">
              Tüm Route&apos;lar
            </h3>
            <p className="mt-1 flex-1 text-sm text-slate-600">
              Site haritasında her sayfanın hangi backend endpoint&apos;ini
              çağırdığını gör.
            </p>
            <div className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-slate-900">
              Site haritasını aç
              <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />
            </div>
          </Link>
        </div>
      </section>
    </div>
  );
}
