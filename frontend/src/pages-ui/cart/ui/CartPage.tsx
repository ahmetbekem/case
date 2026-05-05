'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Trash2 } from 'lucide-react';
import Link from 'next/link';

import { CART_QUERY_KEY, useCart } from '@/entities/cart';
import { ConvertedPrice } from '@/entities/currency';
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
                  {item.productId.slice(0, 8)}…
                </p>
                <p className="text-xs text-slate-500">
                  Birim: {formatMoney(item.unitPrice, item.currency)}
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

      <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button
          variant="outline"
          onClick={() => clearCart.mutate()}
          disabled={clearCart.isPending}
        >
          Sepeti Boşalt
        </Button>
        <div className="flex flex-col items-end gap-2 sm:flex-row sm:items-center sm:gap-6">
          <div className="flex items-center gap-2 text-lg">
            <span>Toplam:</span>
            <ConvertedPrice
              amount={cart.subtotal}
              currency={cart.currency}
              className="text-lg font-bold text-slate-900 items-end"
            />
          </div>
          <Link href="/checkout">
            <Button>Ödemeye Geç</Button>
          </Link>
        </div>
      </div>
    </div>
  );
}
