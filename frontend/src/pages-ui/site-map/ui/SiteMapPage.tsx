'use client';

import Link from 'next/link';

import { useCurrentUser } from '@/entities/user';
import { cn } from '@/shared/lib/cn';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/shared/ui';

import {
  SECTIONS,
  type Access,
  type RouteEntry,
} from '../model/routes';

const accessStyles: Record<Access, string> = {
  public: 'bg-emerald-100 text-emerald-900 ring-emerald-300',
  customer: 'bg-sky-100 text-sky-900 ring-sky-300',
  admin: 'bg-rose-100 text-rose-900 ring-rose-300',
};

const accessLabels: Record<Access, string> = {
  public: 'public',
  customer: 'customer',
  admin: 'admin',
};

function AccessBadge({ access }: { access: Access }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
        accessStyles[access],
      )}
    >
      {accessLabels[access]}
    </span>
  );
}

function RouteCard({
  entry,
  reachable,
}: {
  entry: RouteEntry;
  reachable: boolean;
}) {
  const isLink = !entry.comingSoon && reachable;
  const cardBody = (
    <Card
      className={cn(
        'h-full transition',
        isLink && 'hover:border-slate-400 hover:shadow-sm',
        entry.comingSoon && 'opacity-60',
        !reachable && !entry.comingSoon && 'opacity-70',
      )}
    >
      <CardHeader>
        <div className="flex items-start justify-between gap-2">
          <CardTitle className="break-all font-mono text-sm">
            {entry.path}
          </CardTitle>
          <AccessBadge access={entry.access} />
        </div>
        <CardDescription className="font-semibold text-slate-900">
          {entry.title}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-3 text-sm text-slate-600">
        <p>{entry.description}</p>

        {entry.endpoints.length > 0 && (
          <div>
            <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
              Endpoints
            </p>
            <ul className="space-y-0.5 font-mono text-xs text-slate-500">
              {entry.endpoints.map((ep) => (
                <li key={ep}>{ep}</li>
              ))}
            </ul>
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2 pt-1">
          {entry.comingSoon && (
            <span className="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-inset ring-amber-300">
              Coming Soon
            </span>
          )}
          {entry.badge !== undefined && (
            <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300">
              {entry.badge}
            </span>
          )}
          {!reachable && !entry.comingSoon && (
            <span className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-300">
              Erişim için giriş gerek
            </span>
          )}
        </div>
      </CardContent>
    </Card>
  );

  if (entry.external) {
    return (
      <a href={entry.path} target="_blank" rel="noopener noreferrer">
        {cardBody}
      </a>
    );
  }

  if (isLink) {
    return <Link href={entry.path}>{cardBody}</Link>;
  }

  return cardBody;
}

function isReachable(entry: RouteEntry, role: 'admin' | 'customer' | null): boolean {
  if (entry.access === 'public') return true;
  if (role === null) return false;
  if (entry.access === 'admin') return role === 'admin';
  return true;
}

export function SiteMapPage() {
  const { data: user } = useCurrentUser();
  const role = user?.role ?? null;

  return (
    <div className="mx-auto max-w-7xl space-y-10 px-4 py-8">
      <header className="space-y-2">
        <h1 className="text-3xl font-bold tracking-tight text-slate-900">
          Site Haritası
        </h1>
        <p className="text-sm text-slate-600">
          E-Ticaret Demo Projesi — tüm sayfaların ve API endpoint&apos;lerinin
          rehberi.{' '}
          <Link
            href="https://github.com"
            className="text-slate-900 underline hover:no-underline"
          >
            docs/FRONTEND_GUIDE.md
          </Link>
          {' '}ile birebir senkron.
        </p>
        {role !== null ? (
          <p className="text-xs text-slate-500">
            Şu an <strong>{user?.email}</strong> olarak giriş yaptın
            (rol: <strong>{role}</strong>). Erişebileceğin sayfalar tıklanabilir.
          </p>
        ) : (
          <p className="text-xs text-slate-500">
            Giriş yapmadın — customer/admin sayfaları sadece bilgi amaçlı görünüyor.
          </p>
        )}
      </header>

      {SECTIONS.map((section) => (
        <section key={section.section} className="space-y-4">
          <div>
            <h2 className="text-xl font-semibold text-slate-900">
              {section.section}
            </h2>
            <p className="text-sm text-slate-500">{section.description}</p>
          </div>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {section.routes.map((entry) => (
              <RouteCard
                key={entry.path}
                entry={entry}
                reachable={isReachable(entry, role)}
              />
            ))}
          </div>
        </section>
      ))}

      <section className="rounded-lg border border-slate-200 bg-slate-50 p-6">
        <h3 className="mb-3 text-base font-semibold text-slate-900">
          Test Hesapları
        </h3>
        <p className="mb-4 text-sm text-slate-600">
          <code className="rounded bg-slate-200 px-1 py-0.5 text-xs">
            php artisan db:seed
          </code>{' '}
          ile yüklenir. Tekrar çalıştırılabilir (idempotent — şifre yenilenir).
        </p>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm">
            <p className="mb-1 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900 ring-1 ring-inset ring-rose-300">
              admin
            </p>
            <p className="font-mono text-slate-900">admin@example.test</p>
            <p className="font-mono text-slate-700">Admin123!</p>
            <p className="mt-2 text-xs text-slate-600">
              Tüm sayfalar + /admin/products
            </p>
          </div>
          <div className="rounded-md border border-sky-200 bg-sky-50 p-4 text-sm">
            <p className="mb-1 inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-900 ring-1 ring-inset ring-sky-300">
              customer
            </p>
            <p className="font-mono text-slate-900">customer@example.test</p>
            <p className="font-mono text-slate-700">Customer123!</p>
            <p className="mt-2 text-xs text-slate-600">
              Sepet + sipariş + checkout
            </p>
          </div>
        </div>
      </section>
    </div>
  );
}
