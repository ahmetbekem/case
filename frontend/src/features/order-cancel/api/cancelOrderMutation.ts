'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';

import { ORDER_QUERY_KEY } from '@/entities/order';
import { clientFetch } from '@/shared/api/client';

interface CancelPayload {
  orderId: string;
  reason: string;
}

export function useCancelOrderMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ orderId, reason }: CancelPayload) =>
      clientFetch(`/api/sales/orders/${orderId}/cancel`, {
        method: 'POST',
        body: { reason },
      }),
    onSuccess: async (_data, { orderId }) => {
      await queryClient.invalidateQueries({
        queryKey: ORDER_QUERY_KEY(orderId),
      });
      await queryClient.invalidateQueries({
        queryKey: ['order', 'list'],
        exact: false,
      });
      // Backend cancel anında stokları geri yazar (CancelOrderHandler.restock).
      // Kullanıcı /products veya /admin/products sekmesindeyse cache stale
      // kalmasın diye product list + detail query'lerini de invalidate ediyoruz.
      await queryClient.invalidateQueries({
        queryKey: ['product'],
        exact: false,
      });
      // Sepet snapshot'ı (varsa) da fiyat/stock referansı taşıdığı için tazele
      await queryClient.invalidateQueries({
        queryKey: ['cart'],
        exact: false,
      });
    },
  });
}
