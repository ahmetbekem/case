'use client';

import { useQuery } from '@tanstack/react-query';

import { ClientApiError, clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Cart } from '../model/types';

export const CART_QUERY_KEY = ['cart', 'me'] as const;

/**
 * /sales/cart — backend returns an empty cart (no 404) for fresh users,
 * but on 401 we just surface null so the UI can prompt sign-in.
 */
export function useCart() {
  return useQuery({
    queryKey: CART_QUERY_KEY,
    queryFn: async (): Promise<Cart | null> => {
      try {
        const response = await clientFetch<ApiResponse<Cart>>('/api/sales/cart');
        return response.data;
      } catch (error) {
        if (error instanceof ClientApiError && error.status === 401) {
          return null;
        }
        throw error;
      }
    },
    staleTime: 30_000,
  });
}
