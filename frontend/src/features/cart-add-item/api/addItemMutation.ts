'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';

import { CART_QUERY_KEY } from '@/entities/cart';
import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

interface AddItemPayload {
  productId: string;
  quantity: number;
}

export function useAddItemMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: AddItemPayload) =>
      clientFetch<ApiResponse<unknown>>('/api/sales/cart/items', {
        method: 'POST',
        body: payload,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });
    },
  });
}
