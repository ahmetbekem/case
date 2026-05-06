'use client';

import Link from 'next/link';

import { useProduct } from '@/entities/product';
import { AddToCartButton } from '@/features/cart-add-item';
import { ClientApiError } from '@/shared/api/client';
import { formatMoney } from '@/shared/lib/format';
import { Button, Card, CardContent, CardHeader, CardTitle } from '@/shared/ui';

interface ProductDetailPageProps {
  id: string;
}

export function ProductDetailPage({ id }: ProductDetailPageProps) {
  const { data: product, isLoading, isError, error } = useProduct(id);

  if (isLoading) {
    return (
      <p className="mx-auto max-w-3xl px-4 py-12 text-sm text-slate-500">
        Yükleniyor…
      </p>
    );
  }

  if (isError || product === undefined) {
    const isNotFound =
      error instanceof ClientApiError && error.status === 404;
    return (
      <div className="mx-auto max-w-3xl space-y-4 px-4 py-12">
        <p className="text-lg font-medium text-slate-900">
          {isNotFound ? 'Ürün bulunamadı' : 'Bir hata oluştu'}
        </p>
        <Link href="/products">
          <Button variant="outline">Ürünlere geri dön</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6 px-4 py-8">
      <Card>
        <CardHeader>
          <CardTitle className="text-2xl">{product.name}</CardTitle>
          <p className="text-xs text-slate-500">SKU: {product.sku}</p>
        </CardHeader>
        <CardContent className="space-y-4">
          {product.description !== null && (
            <p className="text-sm text-slate-700">{product.description}</p>
          )}
          <div>
            <p className="text-3xl font-bold text-slate-900">
              {product.displayPrice !== null && product.displayCurrency !== null
                ? formatMoney(product.displayPrice, product.displayCurrency)
                : formatMoney(product.basePrice, product.baseCurrency)}
            </p>
            {product.displayPrice !== null
              && product.displayCurrency !== product.baseCurrency && (
                <p className="text-sm text-slate-500">
                  Base: {formatMoney(product.basePrice, product.baseCurrency)}
                  {product.exchangeRate !== null && (
                    <span> · kur {product.exchangeRate.toFixed(4)}</span>
                  )}
                </p>
              )}
          </div>
          <p
            className={
              product.stock > 0
                ? 'text-sm text-emerald-700'
                : 'text-sm font-medium text-red-700'
            }
          >
            {product.stock > 0 ? `Stokta ${product.stock} adet` : 'Stokta yok'}
          </p>
          <AddToCartButton
            productId={product.id}
            disabled={product.stock <= 0}
            className="max-w-xs"
          />
        </CardContent>
      </Card>

      <Link href="/products">
        <Button variant="ghost">← Tüm ürünler</Button>
      </Link>
    </div>
  );
}
