'use client';

import Link from 'next/link';

import { formatMoney } from '@/shared/lib/format';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/shared/ui';

import type { Product } from '../model/types';

interface ProductCardProps {
  product: Product;
  /** Slot for an "Add to cart" button (features/cart-add-item) */
  action?: React.ReactNode;
}

/**
 * Backend conversion-aware fiyat gösterimi (ADR-016).
 * - displayPrice dolu (header currency != baseCurrency) → display fiyat
 *   üstte, alt satırda küçük gri "Base: X TRY" referansı.
 * - displayPrice null (identity / kur yoksa) → basePrice tek satır.
 *
 * Backend zaten doğru kuru uyguladığı için frontend'de ConvertedPrice
 * (UI-side conversion) kullanmıyoruz — çift conversion riskinden kaçın.
 */
export function ProductCard({ product, action }: ProductCardProps) {
  const inStock = product.stock > 0;

  return (
    <Card className="flex h-full flex-col">
      <CardHeader>
        <Link
          href={`/products/${product.id}`}
          className="hover:underline focus-visible:underline focus-visible:outline-none"
        >
          <CardTitle className="text-base">{product.name}</CardTitle>
        </Link>
        <p className="text-xs text-slate-500">{product.sku}</p>
      </CardHeader>
      <CardContent className="flex-1 space-y-2">
        {product.description !== null && (
          <p className="line-clamp-3 text-sm text-slate-600">
            {product.description}
          </p>
        )}
        <div>
          <p className="text-lg font-semibold text-slate-900">
            {product.displayPrice !== null && product.displayCurrency !== null
              ? formatMoney(product.displayPrice, product.displayCurrency)
              : formatMoney(product.basePrice, product.baseCurrency)}
          </p>
          {product.displayPrice !== null
            && product.displayCurrency !== product.baseCurrency && (
              <p className="text-xs text-slate-500">
                Base: {formatMoney(product.basePrice, product.baseCurrency)}
              </p>
            )}
        </div>
        <p
          className={
            inStock
              ? 'text-xs text-emerald-700'
              : 'text-xs font-medium text-red-700'
          }
        >
          {inStock ? `Stok: ${product.stock}` : 'Stokta yok'}
        </p>
      </CardContent>
      {action !== undefined && <CardFooter>{action}</CardFooter>}
    </Card>
  );
}
