'use client';

import { LogIn, LogOut, ShoppingCart, UserRound } from 'lucide-react';
import Link from 'next/link';

import { useCart } from '@/entities/cart';
import { useCurrentUser } from '@/entities/user';
import { useLogoutMutation } from '@/features/auth-logout';
import { CurrencySwitcher } from '@/features/currency-switch';
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
              {user.role === 'admin' && (
                <Link
                  href="/admin/products"
                  className="rounded-md px-2 py-1 text-sm font-medium text-slate-900 hover:bg-slate-100"
                >
                  Admin
                </Link>
              )}
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
