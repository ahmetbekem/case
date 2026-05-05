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
    },
  });
}
