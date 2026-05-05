'use client';

import Link from 'next/link';
import { useState } from 'react';

import { OrderStatusBadge, useOrderList } from '@/entities/order';
import { useCurrentUser } from '@/entities/user';
import { formatDateTime, formatMoney } from '@/shared/lib/format';
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/shared/ui';

export function OrdersPage() {
  const { data: user } = useCurrentUser();
  const isAdmin = user?.role === 'admin';
  const [adminScope, setAdminScope] = useState(false);
  const [page, setPage] = useState(1);

  const { data, isLoading, isError } = useOrderList({
    page,
    pageSize: 20,
    adminScope: isAdmin && adminScope,
  });

  return (
    <div className="mx-auto max-w-5xl space-y-6 px-4 py-8">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">
            Siparişlerim
          </h1>
          <p className="text-sm text-slate-500">
            {data?.meta.total ?? 0} sipariş
          </p>
        </div>
        {isAdmin && (
          <label className="inline-flex items-center gap-2 text-sm text-slate-700">
            <input
              type="checkbox"
              checked={adminScope}
              onChange={(event) => {
                setAdminScope(event.target.checked);
                setPage(1);
              }}
              className="h-4 w-4 rounded border-slate-300"
            />
            Tüm kullanıcıların siparişlerini göster (admin)
          </label>
        )}
      </div>

      {isLoading && <p className="text-sm text-slate-500">Yükleniyor…</p>}

      {isError && (
        <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          Siparişler yüklenirken hata oluştu.
        </p>
      )}

      {data !== undefined && data.items.length === 0 && (
        <Card>
          <CardContent className="space-y-3 p-8 text-center">
            <p className="text-slate-500">Henüz sipariş yok.</p>
            <Link href="/products">
              <Button variant="outline">Alışverişe başla</Button>
            </Link>
          </CardContent>
        </Card>
      )}

      {data !== undefined && data.items.length > 0 && (
        <>
          <div className="space-y-3">
            {data.items.map((order) => (
              <Card key={order.id}>
                <Link href={`/orders/${order.id}`}>
                  <CardHeader className="flex-row items-center justify-between gap-3 hover:bg-slate-50">
                    <div className="space-y-1">
                      <CardTitle className="text-base">
                        {order.orderNumber}
                      </CardTitle>
                      <CardDescription>
                        {formatDateTime(order.createdAt)} · {order.itemCount}{' '}
                        kalem
                      </CardDescription>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="text-sm font-semibold text-slate-900">
                        {formatMoney(
                          order.displayTotal,
                          order.displayCurrency,
                        )}
                      </span>
                      <OrderStatusBadge status={order.status} />
                    </div>
                  </CardHeader>
                </Link>
              </Card>
            ))}
          </div>

          {data.meta.totalPages > 1 && (
            <div className="flex items-center justify-between">
              <Button
                variant="outline"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
              >
                Önceki
              </Button>
              <span className="text-sm text-slate-600">
                Sayfa {data.meta.page} / {data.meta.totalPages}
              </span>
              <Button
                variant="outline"
                onClick={() =>
                  setPage((p) => Math.min(data.meta.totalPages, p + 1))
                }
                disabled={page >= data.meta.totalPages}
              >
                Sonraki
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
