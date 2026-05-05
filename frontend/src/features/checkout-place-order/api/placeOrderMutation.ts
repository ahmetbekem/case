'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';

import { CART_QUERY_KEY } from '@/entities/cart';
import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

interface PlaceOrderPayload {
  displayCurrency: 'TRY' | 'USD' | 'EUR';
  notes?: string;
}

interface PlacedOrder {
  id: string;
  orderNumber: string;
}

export function usePlaceOrderMutation() {
  const router = useRouter();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: PlaceOrderPayload) =>
      clientFetch<ApiResponse<PlacedOrder>>('/api/sales/orders', {
        method: 'POST',
        body: payload,
      }),
    onSuccess: async (response) => {
      // Cart was converted server-side — empty it locally too. List of
      // user orders has a new entry, so drop that cache as well.
      await queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });
      await queryClient.invalidateQueries({
        queryKey: ['order', 'list'],
        exact: false,
      });
      // PlaceOrderHandler.decreaseStock — sipariş kalemleri stoktan düşürüldü;
      // /products veya /admin/products açıksa stok rakamları stale kalmasın.
      await queryClient.invalidateQueries({
        queryKey: ['product'],
        exact: false,
      });
      router.push(`/orders/${response.data.id}`);
      router.refresh();
    },
  });
}
