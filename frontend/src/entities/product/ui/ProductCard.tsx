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
        <p className="text-lg font-semibold text-slate-900">
          {formatMoney(product.price, product.currency)}
        </p>
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
