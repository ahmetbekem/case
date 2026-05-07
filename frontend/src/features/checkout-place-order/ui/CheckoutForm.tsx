'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

import { useAddresses } from '@/entities/address';
import { useCart } from '@/entities/cart';
import {
  ConvertedPrice,
  useCurrencyStore,
  useSupportedCurrencies,
  type CurrencyCode,
} from '@/entities/currency';
import { AddressSelector } from '@/features/address-selector';
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

/**
 * ADR-018 — Sipariş için adres zorunlu. Kullanıcı varsayılan adresi
 * varsa otomatik seçili gelir; "Fatura adresimle aynı" checkbox
 * default işaretli (UX kısayolu).
 *
 * Adres yoksa: empty state + "Adres ekle" CTA → /profile/addresses.
 */
export function CheckoutForm() {
  const { data: cart, isLoading } = useCart();
  const { data: addresses, isLoading: addressesLoading } = useAddresses();
  const { data: currencies } = useSupportedCurrencies();

  const displayCurrency = useCurrencyStore((s) => s.selected);
  const setDisplayCurrency = useCurrencyStore((s) => s.setSelected);

  const [deliveryId, setDeliveryId] = useState<string | null>(null);
  const [billingId, setBillingId] = useState<string | null>(null);
  const [sameAsDelivery, setSameAsDelivery] = useState(true);
  const [notes, setNotes] = useState('');

  // İlk yüklemede default adres'leri seç
  useEffect(() => {
    if (addresses === undefined) return;
    const defaultDelivery = addresses.find((a) => a.isDefaultDelivery)?.id ?? addresses[0]?.id ?? null;
    const defaultBilling = addresses.find((a) => a.isDefaultBilling)?.id ?? defaultDelivery;
    setDeliveryId((current) => current ?? defaultDelivery);
    setBillingId((current) => current ?? defaultBilling);
    if (defaultDelivery !== null && defaultBilling === defaultDelivery) {
      setSameAsDelivery(true);
    }
  }, [addresses]);

  const mutation = usePlaceOrderMutation();
  const serverError =
    mutation.error instanceof ClientApiError ? mutation.error.message : null;

  if (isLoading || addressesLoading) {
    return <p className="text-sm text-slate-500">Yükleniyor…</p>;
  }

  if (cart === null || cart === undefined || cart.items.length === 0) {
    return (
      <p className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Sepetiniz boş — ödeme yapmadan önce ürün ekleyin.
      </p>
    );
  }

  if (addresses === undefined || addresses.length === 0) {
    return (
      <Card>
        <CardContent className="space-y-3 p-6 text-center">
          <p className="text-slate-700">
            Sipariş verebilmek için en az 1 teslimat adresi eklemelisiniz.
          </p>
          <Link href="/profile/addresses">
            <Button>Adres Ekle</Button>
          </Link>
        </CardContent>
      </Card>
    );
  }

  const effectiveBillingId = sameAsDelivery ? deliveryId : billingId;
  const canSubmit =
    deliveryId !== null &&
    effectiveBillingId !== null &&
    cart.isUniformCurrency &&
    !mutation.isPending;

  function handleSubmit(): void {
    if (deliveryId === null) return;
    mutation.mutate({
      displayCurrency,
      deliveryAddressId: deliveryId,
      billingAddressId: sameAsDelivery ? null : billingId,
      notes: notes.trim() === '' ? undefined : notes.trim(),
    });
  }

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div className="space-y-4 lg:col-span-2">
        <Card>
          <CardHeader className="flex-row items-center justify-between space-y-0">
            <CardTitle className="text-base">Teslimat Adresi</CardTitle>
            <Link href="/profile/addresses" className="text-xs text-slate-600 underline">
              Adres yönet
            </Link>
          </CardHeader>
          <CardContent>
            <AddressSelector
              addresses={addresses}
              selectedId={deliveryId}
              onSelect={setDeliveryId}
              highlight="delivery"
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Fatura Adresi</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={sameAsDelivery}
                onChange={(e) => setSameAsDelivery(e.target.checked)}
                className="h-4 w-4 rounded border-slate-300"
              />
              <span>Teslimat adresimle aynı</span>
            </label>
            {!sameAsDelivery && (
              <AddressSelector
                addresses={addresses}
                selectedId={billingId}
                onSelect={setBillingId}
                highlight="billing"
              />
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Ödeme Bilgileri</CardTitle>
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
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Sipariş Özeti</CardTitle>
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
              tek currency&apos;de oluşturulabilir — lütfen{' '}
              <a href="/cart" className="underline">sepete</a> dönüp ürünleri tek currency&apos;ye düşürün.
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
            disabled={!canSubmit}
            onClick={handleSubmit}
          >
            {mutation.isPending
              ? 'Sipariş veriliyor…'
              : !cart.isUniformCurrency
                ? 'Tek currency gerekli'
                : deliveryId === null
                  ? 'Teslimat adresi seçin'
                  : 'Siparişi Onayla'}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
