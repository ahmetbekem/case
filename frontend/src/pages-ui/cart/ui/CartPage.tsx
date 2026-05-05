'use client';

import { useMutation, useQueries, useQueryClient } from '@tanstack/react-query';
import { Trash2 } from 'lucide-react';
import Link from 'next/link';

import { CART_QUERY_KEY, useCart } from '@/entities/cart';
import {
  ConvertedPrice,
  EXCHANGE_RATE_QUERY_KEY,
  useCurrencyStore,
  type CurrencyCode,
} from '@/entities/currency';
import { clientFetch as cf } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';
import { ClientApiError, clientFetch } from '@/shared/api/client';
import { formatMoney } from '@/shared/lib/format';
import { Button, Card, CardContent, Input } from '@/shared/ui';

/**
 * Update / remove / clear inlined here instead of carved into separate
 * features — they share the cart query cache invalidation, only ever
 * fire from this page, and the markup is short enough that an extra
 * feature folder per click is more ceremony than insight.
 */
export function CartPage() {
  const { data: cart, isLoading, isError } = useCart();
  const qc = useQueryClient();
  const invalidateCart = () =>
    qc.invalidateQueries({ queryKey: CART_QUERY_KEY });

  const update = useMutation({
    mutationFn: async ({
      itemId,
      quantity,
    }: {
      itemId: string;
      quantity: number;
    }) =>
      clientFetch(`/api/sales/cart/items/${itemId}`, {
        method: 'PATCH',
        body: { quantity },
      }),
    onSuccess: invalidateCart,
  });

  const remove = useMutation({
    mutationFn: async (itemId: string) =>
      clientFetch(`/api/sales/cart/items/${itemId}`, { method: 'DELETE' }),
    onSuccess: invalidateCart,
  });

  const clearCart = useMutation({
    mutationFn: async () =>
      clientFetch('/api/sales/cart', { method: 'DELETE' }),
    onSuccess: invalidateCart,
  });

  if (isLoading) {
    return (
      <p className="mx-auto max-w-4xl px-4 py-12 text-sm text-slate-500">
        Yükleniyor…
      </p>
    );
  }

  if (isError) {
    return (
      <p className="mx-auto max-w-4xl px-4 py-12 text-sm text-red-700">
        Sepet yüklenirken hata oluştu.
      </p>
    );
  }

  if (cart === null || cart === undefined) {
    return (
      <div className="mx-auto max-w-4xl space-y-4 px-4 py-12 text-center">
        <p className="text-lg font-medium text-slate-900">Sepetinizi görmek için giriş yapın</p>
        <Link href="/login">
          <Button>Giriş Yap</Button>
        </Link>
      </div>
    );
  }

  if (cart.items.length === 0) {
    return (
      <div className="mx-auto max-w-4xl space-y-4 px-4 py-12 text-center">
        <p className="text-lg font-medium text-slate-900">Sepetiniz boş</p>
        <Link href="/products">
          <Button variant="outline">Alışverişe başla</Button>
        </Link>
      </div>
    );
  }

  const opError =
    update.error instanceof ClientApiError
      ? update.error.message
      : remove.error instanceof ClientApiError
        ? remove.error.message
        : null;

  return (
    <div className="mx-auto max-w-4xl space-y-6 px-4 py-8">
      <h1 className="text-2xl font-bold tracking-tight text-slate-900">Sepetim</h1>

      <Card>
        <CardContent className="divide-y divide-slate-200 p-0">
          {cart.items.map((item) => (
            <div
              key={item.id}
              className="flex items-center gap-4 p-4 sm:gap-6"
            >
              <div className="flex-1 space-y-1">
                <p className="font-medium text-slate-900">
                  {item.productName}
                </p>
                <p className="text-xs text-slate-500">
                  {item.productSku} · Birim:{' '}
                  {formatMoney(item.unitPrice, item.currency)}
                </p>
              </div>

              <Input
                type="number"
                min={1}
                defaultValue={item.quantity}
                onBlur={(event) => {
                  const newQty = Number.parseInt(event.target.value, 10);
                  if (Number.isFinite(newQty) && newQty > 0 && newQty !== item.quantity) {
                    update.mutate({ itemId: item.id, quantity: newQty });
                  }
                }}
                className="w-20"
                disabled={update.isPending}
              />

              <ConvertedPrice
                amount={item.lineTotal}
                currency={item.currency}
                className="w-32 text-right font-medium text-slate-900"
                originalClassName="text-right"
              />

              <Button
                variant="ghost"
                size="icon"
                onClick={() => remove.mutate(item.id)}
                disabled={remove.isPending}
                aria-label="Kaldır"
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          ))}
        </CardContent>
      </Card>

      {opError !== null && (
        <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {opError}
        </p>
      )}

      <CartTotals cart={cart} onClear={() => clearCart.mutate()} clearing={clearCart.isPending} />
    </div>
  );
}

/**
 * Toplam bölgesi — uniform cart için tek ConvertedPrice; mixed cart için
 * her bucket'ı ayrı ConvertedPrice + sum (toplam display currency'de).
 * Mixed durumda checkout butonu disable + uyarı.
 */
function CartTotals({
  cart,
  onClear,
  clearing,
}: {
  cart: import('@/entities/cart').Cart;
  onClear: () => void;
  clearing: boolean;
}) {
  const target = useCurrencyStore((s) => s.selected);
  const buckets = Object.entries(cart.subtotalsByCurrency) as Array<
    [CurrencyCode, number]
  >;

  return (
    <div className="space-y-4">
      {!cart.isUniformCurrency && (
        <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
          Sepetinizde birden fazla para birimi var (
          {buckets.map(([code]) => code).join(', ')}). Aşağıda her birim {target}{' '}
          karşılığına çevrilip toplanır. <strong>Ödemeyi tamamlayabilmek için</strong>{' '}
          tek bir para birimine düşürün (farklı currency&apos;deki ürünleri çıkarın).
        </p>
      )}

      <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button variant="outline" onClick={onClear} disabled={clearing}>
          Sepeti Boşalt
        </Button>
        <div className="flex flex-col items-end gap-3 sm:flex-row sm:items-end sm:gap-6">
          {buckets.length > 1 && (
            <ul className="space-y-0.5 text-xs text-slate-500">
              {buckets.map(([code, amount]) => (
                <li key={code} className="flex justify-between gap-3">
                  <span>{code} ara toplam:</span>
                  <span className="font-mono">{formatMoney(amount, code)}</span>
                </li>
              ))}
            </ul>
          )}
          <div className="flex items-center gap-2 text-lg">
            <span>Toplam:</span>
            <CartGrandTotal buckets={buckets} target={target} />
          </div>
          <Link
            href="/checkout"
            className={!cart.isUniformCurrency ? 'pointer-events-none' : ''}
            aria-disabled={!cart.isUniformCurrency}
          >
            <Button disabled={!cart.isUniformCurrency}>Ödemeye Geç</Button>
          </Link>
        </div>
      </div>
    </div>
  );
}

/**
 * Her bucket'ın display currency karşılığını topla. Tek bucket varsa
 * direk ConvertedPrice kullanır (animasyon + "≈ orijinal" gösterimi
 * için). Mixed'de her bucket için ayrı useExchangeRate çağırır;
 * TanStack dedup yapar.
 */
function CartGrandTotal({
  buckets,
  target,
}: {
  buckets: Array<[CurrencyCode, number]>;
  target: CurrencyCode;
}) {
  if (buckets.length === 0) {
    return (
      <span className="text-lg font-bold text-slate-900">
        {formatMoney(0, target)}
      </span>
    );
  }
  if (buckets.length === 1) {
    const [code, amount] = buckets[0]!;
    return (
      <ConvertedPrice
        amount={amount}
        currency={code}
        className="text-lg font-bold text-slate-900 items-end"
      />
    );
  }
  return <MultiBucketTotal buckets={buckets} target={target} />;
}

function MultiBucketTotal({
  buckets,
  target,
}: {
  buckets: Array<[CurrencyCode, number]>;
  target: CurrencyCode;
}) {
  // useQueries hooks-rules uyumlu (tek hook çağrısı, dinamik array içi).
  // Her bucket için (base→target) kuru fetch eder; aynı target ise rate=1.
  const rates = useQueries({
    queries: buckets.map(([code]) => ({
      queryKey: EXCHANGE_RATE_QUERY_KEY(code, target),
      queryFn: async (): Promise<number> => {
        if (code === target) return 1;
        try {
          const r = await cf<ApiResponse<{ rate: number }>>(
            `/api/currencies/${code}/${target}`,
          );
          return r.data.rate;
        } catch {
          // Inverse fallback (TRY/USD biliniyorsa USD/TRY = 1/rate)
          const inv = await cf<ApiResponse<{ rate: number }>>(
            `/api/currencies/${target}/${code}`,
          );
          return inv.data.rate === 0 ? 1 : 1 / inv.data.rate;
        }
      },
      staleTime: 5 * 60 * 1000,
      retry: false,
    })),
  });

  const allReady = rates.every((r) => r.data !== undefined);
  if (!allReady) {
    return <span className="text-lg font-bold text-slate-400">…</span>;
  }
  const total = buckets.reduce((sum, [, amount], i) => {
    const rate = rates[i]?.data ?? 1;
    return sum + amount * rate;
  }, 0);
  return (
    <span className="text-lg font-bold text-slate-900">
      {formatMoney(total, target)}
    </span>
  );
}
