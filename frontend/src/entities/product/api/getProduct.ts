'use client';

import { useQuery } from '@tanstack/react-query';

import { useCurrencyStore } from '@/entities/currency';
import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Product } from '../model/types';

export const PRODUCT_QUERY_KEY = (id: string, displayCurrency: string) =>
  ['product', 'detail', id, displayCurrency] as const;

export function useProduct(id: string) {
  const displayCurrency = useCurrencyStore((s) => s.selected);

  return useQuery({
    queryKey: PRODUCT_QUERY_KEY(id, displayCurrency),
    queryFn: async () => {
      const response = await clientFetch<ApiResponse<Product>>(
        `/api/catalog/products/${id}?displayCurrency=${displayCurrency}`,
      );
      return response.data;
    },
    enabled: id !== '',
  });
}
