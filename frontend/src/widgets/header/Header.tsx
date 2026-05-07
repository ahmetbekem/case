'use client';

import {
  ChevronDown,
  LogIn,
  LogOut,
  Settings,
  ShoppingCart,
  Store,
  UserRound,
} from 'lucide-react';
import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';

import { useCart } from '@/entities/cart';
import { useCurrentUser } from '@/entities/user';
import { useLogoutMutation } from '@/features/auth-logout';
import { CurrencySwitcher } from '@/features/currency-switch';
import { cn } from '@/shared/lib/cn';
import { Button } from '@/shared/ui';

export function Header() {
  const { data: user } = useCurrentUser();
  const { data: cart } = useCart();
  const logout = useLogoutMutation();

  const cartCount = cart?.totalQuantity ?? 0;
  const isLoggedIn = user !== null && user !== undefined;

  return (
    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <Link
          href={isLoggedIn ? '/products' : '/'}
          className="text-lg font-bold tracking-tight text-slate-900"
        >
          Dehasoft
        </Link>

        <nav className="flex items-center gap-2 sm:gap-4">
          <CurrencySwitcher />

          <Link
            href="/cart"
            className="relative inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm text-slate-700 hover:bg-slate-100"
            aria-label={`Sepetim (${cartCount})`}
          >
            <ShoppingCart className="h-5 w-5" />
            <span className="font-medium">{cartCount}</span>
          </Link>

          {isLoggedIn ? (
            <>
              <span className="hidden items-center gap-1 text-sm text-slate-700 sm:inline-flex">
                <UserRound className="h-4 w-4" />
                {user.fullName}
              </span>
              <Link
                href="/orders"
                className="rounded-md px-2 py-1 text-sm text-slate-700 hover:bg-slate-100"
              >
                Siparişler
              </Link>
              <Link
                href="/profile/addresses"
                className="rounded-md px-2 py-1 text-sm text-slate-700 hover:bg-slate-100"
              >
                Adreslerim
              </Link>
              {user.role === 'admin' && <AdminMenu />}
              <Button
                variant="ghost"
                size="sm"
                onClick={() => logout.mutate()}
                disabled={logout.isPending}
                aria-label="Çıkış yap"
              >
                <LogOut className="h-4 w-4" />
              </Button>
            </>
          ) : (
            <Link
              href="/login"
              className="inline-flex items-center gap-1 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800"
            >
              <LogIn className="h-4 w-4" />
              Giriş
            </Link>
          )}
        </nav>
      </div>
    </header>
  );
}

interface AdminMenuItem {
  href: string;
  label: string;
  icon: typeof Settings;
}

const ADMIN_ITEMS: ReadonlyArray<AdminMenuItem> = [
  { href: '/admin/products', label: 'Ürün Yönetimi', icon: Store },
  { href: '/admin/settings', label: 'Sistem Ayarları', icon: Settings },
];

/**
 * Click-toggle dropdown — outside-click + Escape ile kapanır.
 * shadcn DropdownMenu kullanmadık (radix pull bağımlılığı taşımamak
 * için); minimal native state yeterli.
 */
function AdminMenu() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;

    function onDocClick(event: MouseEvent) {
      if (ref.current !== null && !ref.current.contains(event.target as Node)) {
        setOpen(false);
      }
    }
    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') setOpen(false);
    }

    document.addEventListener('mousedown', onDocClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDocClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="menu"
        aria-expanded={open}
        className={cn(
          'inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-medium text-slate-900 hover:bg-slate-100',
          open && 'bg-slate-100',
        )}
      >
        Admin
        <ChevronDown
          className={cn(
            'h-3.5 w-3.5 transition-transform',
            open && 'rotate-180',
          )}
        />
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 top-full z-30 mt-1 w-56 overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg"
        >
          {ADMIN_ITEMS.map(({ href, label, icon: Icon }) => (
            <Link
              key={href}
              href={href}
              role="menuitem"
              onClick={() => setOpen(false)}
              className="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
            >
              <Icon className="h-4 w-4 text-slate-500" />
              {label}
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
