'use client';

import { useQuery } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Product, ProductListMeta } from '../model/types';

export interface ListProductsParams {
  page?: number;
  pageSize?: number;
  search?: string;
}

export const PRODUCT_LIST_QUERY_KEY = (params: ListProductsParams) =>
  ['product', 'list', params] as const;

export function useProductList(params: ListProductsParams = {}) {
  return useQuery({
    queryKey: PRODUCT_LIST_QUERY_KEY(params),
    queryFn: async () => {
      const search = new URLSearchParams();
      if (params.page !== undefined) search.set('page', String(params.page));
      if (params.pageSize !== undefined)
        search.set('pageSize', String(params.pageSize));
      if (params.search !== undefined && params.search !== '')
        search.set('search', params.search);

      const url = `/api/catalog/products${search.toString() === '' ? '' : `?${search.toString()}`}`;

      const response = await clientFetch<
        ApiResponse<Product[]> & { meta: ProductListMeta }
      >(url);
      return { items: response.data, meta: response.meta };
    },
    staleTime: 60_000,
  });
}
