'use client';

import { useState } from 'react';

import { useCart } from '@/entities/cart';
import {
  ConvertedPrice,
  useCurrencyStore,
  useSupportedCurrencies,
  type CurrencyCode,
} from '@/entities/currency';
import { ClientApiError } from '@/shared/api/client';
import { formatMoney } from '@/shared/lib/format';
import {
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Label,
} from '@/shared/ui';

import { usePlaceOrderMutation } from '../api/placeOrderMutation';

export function CheckoutForm() {
  const { data: cart, isLoading } = useCart();
  const { data: currencies } = useSupportedCurrencies();

  // Display currency = global header currency'si. Form'daki selector
  // store'a yazar; başka bir sekmede header'dan değişirse burası da
  // anında yansıtır (zustand subscribe). Sipariş bu para biriminde
  // dondurulur (PlaceOrderHandler.exchangeRateSnapshot).
  const displayCurrency = useCurrencyStore((s) => s.selected);
  const setDisplayCurrency = useCurrencyStore((s) => s.setSelected);

  const [notes, setNotes] = useState('');

  const mutation = usePlaceOrderMutation();
  const serverError =
    mutation.error instanceof ClientApiError ? mutation.error.message : null;

  if (isLoading) {
    return <p className="text-sm text-slate-500">Yükleniyor…</p>;
  }

  if (cart === null || cart === undefined || cart.items.length === 0) {
    return (
      <p className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Sepetiniz boş — ödeme yapmadan önce ürün ekleyin.
      </p>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <Card className="lg:col-span-2">
        <CardHeader>
          <CardTitle>Ödeme Bilgileri</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="displayCurrency">Görüntü Para Birimi</Label>
            <select
              id="displayCurrency"
              value={displayCurrency}
              onChange={(event) =>
                setDisplayCurrency(event.target.value as CurrencyCode)
              }
              className="flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
            >
              {(currencies ?? [
                { code: 'TRY' as const, symbol: '₺' },
                { code: 'USD' as const, symbol: '$' },
                { code: 'EUR' as const, symbol: '€' },
              ]).map((c) => (
                <option key={c.code} value={c.code}>
                  {c.symbol} {c.code}
                </option>
              ))}
            </select>
            <p className="text-xs text-slate-500">
              Sipariş bu para biriminde dondurulacak. Header üzerindeki
              switcher ile aynı değer; biri değişirse diğeri de senkron olur.
            </p>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="notes">Notlar (opsiyonel)</Label>
            <textarea
              id="notes"
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
              maxLength={5000}
              rows={3}
              className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
              placeholder="Sipariş hakkında not bırakın…"
            />
          </div>

          {serverError !== null && (
            <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {serverError}
            </p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Sipariş Özeti</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3 text-sm">
          {cart.items.map((item) => (
            <div key={item.id} className="flex items-start justify-between gap-2">
              <span className="truncate pr-2">
                {item.quantity} × {item.productName}
              </span>
              <ConvertedPrice
                amount={item.lineTotal}
                currency={item.currency}
                showOriginal={false}
                className="text-sm items-end"
              />
            </div>
          ))}
          {!cart.isUniformCurrency && (
            <div className="rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-900">
              Sepetinizde birden fazla para birimi var (
              {Object.keys(cart.subtotalsByCurrency).join(', ')}). Sipariş yalnızca
              tek currency&apos;de oluşturulabilir — lütfen <a href="/cart" className="underline">sepete</a> dönüp ürünleri tek currency&apos;ye düşürün.
            </div>
          )}
          {cart.isUniformCurrency && (
            <>
              <div className="flex items-start justify-between border-t border-slate-200 pt-3 text-base font-semibold text-slate-900">
                <span>Toplam</span>
                <ConvertedPrice
                  amount={cart.subtotal}
                  currency={cart.currency}
                  className="text-base font-semibold text-slate-900 items-end"
                />
              </div>
              <p className="text-xs text-slate-500">
                Sepet base tutarı: {formatMoney(cart.subtotal, cart.currency)}.
                Sipariş anında {displayCurrency} kuru dondurulacak.
              </p>
            </>
          )}
          <Button
            type="button"
            className="w-full"
            disabled={mutation.isPending || !cart.isUniformCurrency}
            onClick={() =>
              mutation.mutate({
                displayCurrency,
                notes: notes.trim() === '' ? undefined : notes.trim(),
              })
            }
          >
            {mutation.isPending
              ? 'Sipariş veriliyor…'
              : cart.isUniformCurrency
                ? 'Siparişi Onayla'
                : 'Tek currency gerekli'}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
