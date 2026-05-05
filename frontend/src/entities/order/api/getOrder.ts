'use client';

import { useQuery } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Order } from '../model/types';

export const ORDER_QUERY_KEY = (id: string) => ['order', 'detail', id] as const;

export function useOrder(id: string) {
  return useQuery({
    queryKey: ORDER_QUERY_KEY(id),
    queryFn: async () => {
      const response = await clientFetch<ApiResponse<Order>>(
        `/api/sales/orders/${id}`,
      );
      return response.data;
    },
    enabled: id !== '',
  });
}
