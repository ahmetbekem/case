'use client';

import { useState } from 'react';

import { ProductCard, useProductList } from '@/entities/product';
import { AddToCartButton } from '@/features/cart-add-item';
import { Button, Input } from '@/shared/ui';

export function ProductListPage() {
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);

  const { data, isLoading, isError, error } = useProductList({
    page,
    pageSize: 12,
    search: debouncedSearch === '' ? undefined : debouncedSearch,
  });

  function onSearchSubmit(event: React.FormEvent) {
    event.preventDefault();
    setDebouncedSearch(search);
    setPage(1);
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6 px-4 py-8">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">
            Ürünler
          </h1>
          <p className="text-sm text-slate-500">
            {data?.meta.total ?? 0} ürün
          </p>
        </div>
        <form onSubmit={onSearchSubmit} className="flex gap-2 sm:w-80">
          <Input
            type="search"
            placeholder="Ürün ara…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
          <Button type="submit" variant="outline">
            Ara
          </Button>
        </form>
      </div>

      {isLoading && (
        <p className="text-sm text-slate-500">Yükleniyor…</p>
      )}

      {isError && (
        <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          Ürünler yüklenirken hata oluştu: {(error as Error).message}
        </p>
      )}

      {data !== undefined && data.items.length === 0 && (
        <p className="rounded-md border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
          Eşleşen ürün bulunamadı.
        </p>
      )}

      {data !== undefined && data.items.length > 0 && (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {data.items.map((product) => (
              <ProductCard
                key={product.id}
                product={product}
                action={
                  <AddToCartButton
                    productId={product.id}
                    disabled={product.stock <= 0}
                    className="w-full"
                  />
                }
              />
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
