'use client';

import Link from 'next/link';
import { useState } from 'react';

import { OrderStatusBadge, useOrder } from '@/entities/order';
import { useCancelOrderMutation } from '@/features/order-cancel';
import { ClientApiError } from '@/shared/api/client';
import { formatDateTime, formatMoney } from '@/shared/lib/format';
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Input,
  Label,
} from '@/shared/ui';

interface OrderDetailPageProps {
  id: string;
}

export function OrderDetailPage({ id }: OrderDetailPageProps) {
  const { data: order, isLoading, isError, error } = useOrder(id);
  const cancel = useCancelOrderMutation();
  const [reason, setReason] = useState('');
  const [showCancelForm, setShowCancelForm] = useState(false);

  if (isLoading) {
    return (
      <p className="mx-auto max-w-3xl px-4 py-12 text-sm text-slate-500">
        Yükleniyor…
      </p>
    );
  }

  if (isError || order === undefined) {
    const isNotFound = error instanceof ClientApiError && error.status === 404;
    return (
      <div className="mx-auto max-w-3xl space-y-4 px-4 py-12">
        <p className="text-lg font-medium text-slate-900">
          {isNotFound ? 'Sipariş bulunamadı' : 'Bir hata oluştu'}
        </p>
        <Link href="/orders">
          <Button variant="outline">Siparişlere geri dön</Button>
        </Link>
      </div>
    );
  }

  const cancelError =
    cancel.error instanceof ClientApiError ? cancel.error.message : null;

  function onCancelSubmit() {
    if (reason.trim().length < 3) {
      return;
    }
    cancel.mutate(
      { orderId: order!.id, reason: reason.trim() },
      {
        onSuccess: () => {
          setShowCancelForm(false);
          setReason('');
        },
      },
    );
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6 px-4 py-8">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="space-y-1">
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">
            {order.orderNumber}
          </h1>
          <p className="text-sm text-slate-500">
            {formatDateTime(order.createdAt)}
          </p>
        </div>
        <OrderStatusBadge status={order.status} />
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Kalemler</CardTitle>
          <CardDescription>{order.itemCount} kalem</CardDescription>
        </CardHeader>
        <CardContent className="divide-y divide-slate-200 p-0">
          {order.items.map((item) => (
            <div
              key={item.id}
              className="flex items-center justify-between gap-4 p-4"
            >
              <div className="space-y-0.5">
                <p className="font-medium text-slate-900">{item.productName}</p>
                <p className="text-xs text-slate-500">
                  SKU: {item.productSku} · {item.quantity} ×{' '}
                  {formatMoney(item.unitPrice, item.currency)}
                </p>
              </div>
              <p className="font-medium text-slate-900">
                {formatMoney(item.lineTotal, item.currency)}
              </p>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Toplam</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <div className="flex justify-between">
            <span className="text-slate-600">Ara toplam</span>
            <span className="font-medium text-slate-900">
              {formatMoney(order.subtotal, order.currency)}
            </span>
          </div>
          <div className="flex justify-between">
            <span className="text-slate-600">
              Görüntü ({order.displayCurrency} · kur{' '}
              {order.exchangeRateSnapshot.toFixed(4)})
            </span>
            <span className="text-base font-bold text-slate-900">
              {formatMoney(order.displayTotal, order.displayCurrency)}
            </span>
          </div>
          {order.notes !== null && (
            <div className="border-t border-slate-200 pt-2">
              <p className="text-xs text-slate-500">Notlar:</p>
              <p className="text-sm text-slate-700">{order.notes}</p>
            </div>
          )}
          {order.cancelledAt !== null && (
            <div className="border-t border-slate-200 pt-2 text-sm text-red-700">
              <p>İptal edildi: {formatDateTime(order.cancelledAt)}</p>
              {order.cancelledReason !== null && (
                <p className="text-xs">{order.cancelledReason}</p>
              )}
            </div>
          )}
        </CardContent>
      </Card>

      {order.status === 'pending' && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Siparişi İptal Et</CardTitle>
            <CardDescription>
              Yalnızca beklemedeki siparişler iptal edilebilir.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {!showCancelForm ? (
              <Button
                variant="destructive"
                onClick={() => setShowCancelForm(true)}
              >
                İptal Et
              </Button>
            ) : (
              <>
                <div className="space-y-1.5">
                  <Label htmlFor="reason">İptal sebebi</Label>
                  <Input
                    id="reason"
                    type="text"
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                    maxLength={500}
                    minLength={3}
                  />
                </div>
                {cancelError !== null && (
                  <p className="text-sm text-red-700">{cancelError}</p>
                )}
                <div className="flex gap-2">
                  <Button
                    variant="destructive"
                    onClick={onCancelSubmit}
                    disabled={cancel.isPending || reason.trim().length < 3}
                  >
                    {cancel.isPending ? 'İptal ediliyor…' : 'İptali Onayla'}
                  </Button>
                  <Button
                    variant="ghost"
                    onClick={() => setShowCancelForm(false)}
                  >
                    Vazgeç
                  </Button>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      )}

      <Link href="/orders">
        <Button variant="ghost">← Tüm siparişler</Button>
      </Link>
    </div>
  );
}
