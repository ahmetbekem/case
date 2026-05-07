'use client';

import { useQuery } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Cart } from '../model/types';

export const CART_QUERY_KEY = ['cart', 'me'] as const;

/**
 * /sales/cart — backend her zaman bir cart döner (ADR-017): user için
 * boş cart, guest için cookie üreterek boş cart. 401 yok.
 */
export function useCart() {
  return useQuery({
    queryKey: CART_QUERY_KEY,
    queryFn: async (): Promise<Cart> => {
      const response = await clientFetch<ApiResponse<Cart>>('/api/sales/cart');
      return response.data;
    },
    staleTime: 30_000,
  });
}
