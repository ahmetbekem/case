'use client';

import { useQuery } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Product } from '../model/types';

export const PRODUCT_QUERY_KEY = (id: string) => ['product', 'detail', id] as const;

export function useProduct(id: string) {
  return useQuery({
    queryKey: PRODUCT_QUERY_KEY(id),
    queryFn: async () => {
      const response = await clientFetch<ApiResponse<Product>>(
        `/api/catalog/products/${id}`,
      );
      return response.data;
    },
    enabled: id !== '',
  });
}
