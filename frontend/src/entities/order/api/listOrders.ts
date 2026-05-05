'use client';

import { useQuery } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Order, OrderListMeta } from '../model/types';

export interface ListOrdersParams {
  page?: number;
  pageSize?: number;
  adminScope?: boolean;
}

export const ORDER_LIST_QUERY_KEY = (params: ListOrdersParams) =>
  ['order', 'list', params] as const;

export function useOrderList(params: ListOrdersParams = {}) {
  return useQuery({
    queryKey: ORDER_LIST_QUERY_KEY(params),
    queryFn: async () => {
      const search = new URLSearchParams();
      if (params.page !== undefined) search.set('page', String(params.page));
      if (params.pageSize !== undefined)
        search.set('pageSize', String(params.pageSize));
      if (params.adminScope === true) search.set('adminScope', '1');

      const url = `/api/sales/orders${search.toString() === '' ? '' : `?${search.toString()}`}`;
      const response = await clientFetch<
        ApiResponse<Order[]> & { meta: OrderListMeta }
      >(url);
      return { items: response.data, meta: response.meta };
    },
  });
}
