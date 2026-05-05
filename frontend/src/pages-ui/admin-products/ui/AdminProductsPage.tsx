'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Check, Pencil, Trash2, X } from 'lucide-react';
import { useState } from 'react';

import {
  PRODUCT_LIST_QUERY_KEY,
  useProductList,
  type Product,
} from '@/entities/product';
import { useCurrentUser } from '@/entities/user';
import { ClientApiError, clientFetch } from '@/shared/api/client';
import { cn } from '@/shared/lib/cn';
import { formatMoney } from '@/shared/lib/format';
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

interface CreateProductValues {
  sku: string;
  name: string;
  description: string;
  price: string;
  currency: string;
  stock: string;
}

interface EditValues {
  name: string;
  description: string;
  price: string;
  currency: string;
  isActive: boolean;
}

const initialCreate: CreateProductValues = {
  sku: '',
  name: '',
  description: '',
  price: '',
  currency: 'TRY',
  stock: '',
};

function toEditValues(p: Product): EditValues {
  return {
    name: p.name,
    description: p.description ?? '',
    price: String(p.price),
    currency: p.currency,
    isActive: p.isActive,
  };
}

export function AdminProductsPage() {
  const { data: user, isLoading: userLoading } = useCurrentUser();
  const [page, setPage] = useState(1);
  const queryClient = useQueryClient();
  const params = { page, pageSize: 20 };

  const list = useProductList(params);
  const [values, setValues] = useState<CreateProductValues>(initialCreate);

  // Inline edit mode — aynı anda yalnızca tek satır
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editValues, setEditValues] = useState<EditValues | null>(null);

  const create = useMutation({
    mutationFn: async (input: CreateProductValues) =>
      clientFetch('/api/catalog/products', {
        method: 'POST',
        body: {
          sku: input.sku.trim().toUpperCase(),
          name: input.name.trim(),
          description: input.description.trim() === '' ? null : input.description.trim(),
          price: Number(input.price),
          currency: input.currency,
          stock: Number(input.stock),
        },
      }),
    onSuccess: () => {
      setValues(initialCreate);
      queryClient.invalidateQueries({
        queryKey: PRODUCT_LIST_QUERY_KEY(params),
      });
    },
  });

  const update = useMutation({
    mutationFn: async ({
      id,
      original,
      values,
    }: {
      id: string;
      original: Product;
      values: EditValues;
    }) => {
      // Yalnızca değişen alanları PATCH'le — backend partial update destekliyor
      // (UpdateProductRequest tüm alanlar `sometimes`); ağ trafiği + audit log gürültüsü azalır.
      const payload: Record<string, unknown> = {};
      const trimmedName = values.name.trim();
      if (trimmedName !== original.name) payload.name = trimmedName;

      const trimmedDesc = values.description.trim();
      const newDesc = trimmedDesc === '' ? null : trimmedDesc;
      if (newDesc !== original.description) payload.description = newDesc;

      const numericPrice = Number(values.price);
      if (numericPrice !== original.price) payload.price = numericPrice;

      if (values.currency !== original.currency) payload.currency = values.currency;
      if (values.isActive !== original.isActive) payload.isActive = values.isActive;

      if (Object.keys(payload).length === 0) {
        return null; // Hiçbir şey değişmemişse network'e çıkma
      }
      return clientFetch(`/api/catalog/products/${id}`, {
        method: 'PATCH',
        body: payload,
      });
    },
    onSuccess: () => {
      setEditingId(null);
      setEditValues(null);
      queryClient.invalidateQueries({
        queryKey: PRODUCT_LIST_QUERY_KEY(params),
      });
    },
  });

  const remove = useMutation({
    mutationFn: async (productId: string) =>
      clientFetch(`/api/catalog/products/${productId}`, { method: 'DELETE' }),
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: PRODUCT_LIST_QUERY_KEY(params),
      }),
  });

  const adjustStock = useMutation({
    mutationFn: async ({ id, delta }: { id: string; delta: number }) =>
      clientFetch(`/api/catalog/products/${id}/stock`, {
        method: 'POST',
        body: { delta },
      }),
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: PRODUCT_LIST_QUERY_KEY(params),
      }),
  });

  if (userLoading) {
    return <p className="px-4 py-12 text-sm text-slate-500">Yükleniyor…</p>;
  }

  if (user === null || user === undefined || user.role !== 'admin') {
    return (
      <p className="mx-auto max-w-3xl px-4 py-12 text-sm text-red-700">
        Bu sayfaya erişim için admin yetkisi gerekli.
      </p>
    );
  }

  const createError =
    create.error instanceof ClientApiError ? create.error.message : null;
  const opError =
    remove.error instanceof ClientApiError
      ? remove.error.message
      : adjustStock.error instanceof ClientApiError
        ? adjustStock.error.message
        : update.error instanceof ClientApiError
          ? update.error.message
          : null;

  function startEdit(product: Product) {
    setEditingId(product.id);
    setEditValues(toEditValues(product));
  }

  function cancelEdit() {
    setEditingId(null);
    setEditValues(null);
  }

  function saveEdit(product: Product) {
    if (editValues === null) return;
    update.mutate({ id: product.id, original: product, values: editValues });
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6 px-4 py-8">
      <h1 className="text-2xl font-bold tracking-tight text-slate-900">
        Ürün Yönetimi
      </h1>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Yeni Ürün</CardTitle>
          <CardDescription>SKU otomatik olarak büyük harfe çevrilir.</CardDescription>
        </CardHeader>
        <CardContent>
          <form
            className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6"
            onSubmit={(event) => {
              event.preventDefault();
              create.mutate(values);
            }}
          >
            <div className="space-y-1.5">
              <Label htmlFor="sku">SKU</Label>
              <Input
                id="sku"
                value={values.sku}
                onChange={(e) => setValues((v) => ({ ...v, sku: e.target.value }))}
                required
              />
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label htmlFor="name">Ad</Label>
              <Input
                id="name"
                value={values.name}
                onChange={(e) => setValues((v) => ({ ...v, name: e.target.value }))}
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="price">Fiyat</Label>
              <Input
                id="price"
                type="number"
                step="0.01"
                min="0"
                value={values.price}
                onChange={(e) => setValues((v) => ({ ...v, price: e.target.value }))}
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="currency">Para</Label>
              <select
                id="currency"
                value={values.currency}
                onChange={(e) =>
                  setValues((v) => ({ ...v, currency: e.target.value }))
                }
                className="flex h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
              >
                <option value="TRY">TRY</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
              </select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="stock">Stok</Label>
              <Input
                id="stock"
                type="number"
                min="0"
                value={values.stock}
                onChange={(e) => setValues((v) => ({ ...v, stock: e.target.value }))}
                required
              />
            </div>
            <div className="space-y-1.5 sm:col-span-2 lg:col-span-6">
              <Label htmlFor="description">Açıklama (opsiyonel)</Label>
              <textarea
                id="description"
                value={values.description}
                onChange={(e) =>
                  setValues((v) => ({ ...v, description: e.target.value }))
                }
                maxLength={5000}
                rows={2}
                className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
              />
            </div>
            {createError !== null && (
              <p className="rounded-md border border-red-200 bg-red-50 p-2 text-xs text-red-700 lg:col-span-6">
                {createError}
              </p>
            )}
            <div className="lg:col-span-6">
              <Button type="submit" disabled={create.isPending}>
                {create.isPending ? 'Ekleniyor…' : 'Ürünü Ekle'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      {opError !== null && (
        <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {opError}
        </p>
      )}

      {list.isLoading && <p className="text-sm text-slate-500">Yükleniyor…</p>}

      {list.data !== undefined && (
        <Card>
          <CardContent className="divide-y divide-slate-200 p-0">
            {list.data.items.map((product: Product) => {
              const isEditing = editingId === product.id;

              if (isEditing && editValues !== null) {
                return (
                  <form
                    key={product.id}
                    className="grid grid-cols-1 gap-3 bg-slate-50 p-4 sm:grid-cols-6"
                    onSubmit={(event) => {
                      event.preventDefault();
                      saveEdit(product);
                    }}
                  >
                    <div className="space-y-1 sm:col-span-3">
                      <Label htmlFor={`name-${product.id}`} className="text-xs">
                        Ad
                      </Label>
                      <Input
                        id={`name-${product.id}`}
                        value={editValues.name}
                        onChange={(e) =>
                          setEditValues((v) =>
                            v === null ? v : { ...v, name: e.target.value },
                          )
                        }
                        minLength={2}
                        maxLength={255}
                        required
                      />
                    </div>
                    <div className="space-y-1 sm:col-span-2">
                      <Label htmlFor={`price-${product.id}`} className="text-xs">
                        Fiyat
                      </Label>
                      <Input
                        id={`price-${product.id}`}
                        type="number"
                        step="0.01"
                        min="0"
                        value={editValues.price}
                        onChange={(e) =>
                          setEditValues((v) =>
                            v === null ? v : { ...v, price: e.target.value },
                          )
                        }
                        required
                      />
                    </div>
                    <div className="space-y-1">
                      <Label
                        htmlFor={`currency-${product.id}`}
                        className="text-xs"
                      >
                        Para
                      </Label>
                      <select
                        id={`currency-${product.id}`}
                        value={editValues.currency}
                        onChange={(e) =>
                          setEditValues((v) =>
                            v === null
                              ? v
                              : { ...v, currency: e.target.value },
                          )
                        }
                        className="flex h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
                      >
                        <option value="TRY">TRY</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                      </select>
                    </div>
                    <div className="space-y-1 sm:col-span-6">
                      <Label
                        htmlFor={`description-${product.id}`}
                        className="text-xs"
                      >
                        Açıklama
                      </Label>
                      <textarea
                        id={`description-${product.id}`}
                        value={editValues.description}
                        onChange={(e) =>
                          setEditValues((v) =>
                            v === null
                              ? v
                              : { ...v, description: e.target.value },
                          )
                        }
                        maxLength={5000}
                        rows={2}
                        className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
                      />
                    </div>
                    <div className="flex flex-wrap items-center gap-3 sm:col-span-6">
                      <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input
                          type="checkbox"
                          checked={editValues.isActive}
                          onChange={(e) =>
                            setEditValues((v) =>
                              v === null
                                ? v
                                : { ...v, isActive: e.target.checked },
                            )
                          }
                          className="h-4 w-4 rounded border-slate-300"
                        />
                        Aktif
                      </label>
                      <span className="text-xs text-slate-500">
                        Stok ({product.stock}) ve SKU bu formdan değiştirilmez —
                        sırasıyla +/− butonları ve ürünü yeniden oluşturma kullanılır.
                      </span>
                    </div>
                    <div className="flex gap-2 sm:col-span-6">
                      <Button
                        type="submit"
                        size="sm"
                        disabled={update.isPending}
                      >
                        <Check className="mr-1 h-4 w-4" />
                        {update.isPending ? 'Kaydediliyor…' : 'Kaydet'}
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={cancelEdit}
                        disabled={update.isPending}
                      >
                        <X className="mr-1 h-4 w-4" />
                        Vazgeç
                      </Button>
                    </div>
                  </form>
                );
              }

              return (
                <div
                  key={product.id}
                  className="flex flex-wrap items-center gap-3 p-4 sm:gap-4"
                >
                  <div className="min-w-0 flex-1">
                    <p
                      className={cn(
                        'truncate font-medium',
                        product.isActive ? 'text-slate-900' : 'text-slate-500 line-through',
                      )}
                    >
                      {product.name}
                    </p>
                    <p className="text-xs text-slate-500">
                      {product.sku} ·{' '}
                      {formatMoney(product.price, product.currency)}
                      {!product.isActive && (
                        <span className="ml-2 inline-flex items-center rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-700">
                          pasif
                        </span>
                      )}
                    </p>
                  </div>
                  <div className="flex items-center gap-1">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        adjustStock.mutate({ id: product.id, delta: -1 })
                      }
                      disabled={adjustStock.isPending || product.stock <= 0}
                    >
                      −
                    </Button>
                    <span className="w-12 text-center text-sm font-medium">
                      {product.stock}
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        adjustStock.mutate({ id: product.id, delta: 1 })
                      }
                      disabled={adjustStock.isPending}
                    >
                      +
                    </Button>
                  </div>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => startEdit(product)}
                    disabled={editingId !== null}
                    aria-label="Düzenle"
                  >
                    <Pencil className="h-4 w-4 text-slate-700" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => {
                      if (confirm(`'${product.name}' silinsin mi?`)) {
                        remove.mutate(product.id);
                      }
                    }}
                    disabled={remove.isPending || editingId !== null}
                    aria-label="Sil"
                  >
                    <Trash2 className="h-4 w-4 text-red-700" />
                  </Button>
                </div>
              );
            })}
            {list.data.items.length === 0 && (
              <p className="p-6 text-center text-sm text-slate-500">
                Henüz ürün yok.
              </p>
            )}
          </CardContent>
        </Card>
      )}

      {list.data !== undefined && list.data.meta.totalPages > 1 && (
        <div className="flex items-center justify-between">
          <Button
            variant="outline"
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page <= 1}
          >
            Önceki
          </Button>
          <span className="text-sm text-slate-600">
            Sayfa {list.data.meta.page} / {list.data.meta.totalPages}
          </span>
          <Button
            variant="outline"
            onClick={() =>
              setPage((p) => Math.min(list.data.meta.totalPages, p + 1))
            }
            disabled={page >= list.data.meta.totalPages}
          >
            Sonraki
          </Button>
        </div>
      )}
    </div>
  );
}
