'use client';

import { useQuery } from '@tanstack/react-query';

import { useCurrencyStore } from '@/entities/currency';
import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Product, ProductListMeta } from '../model/types';

export interface ListProductsParams {
  page?: number;
  pageSize?: number;
  search?: string;
}

export const PRODUCT_LIST_QUERY_KEY = (
  params: ListProductsParams,
  displayCurrency: string,
) => ['product', 'list', { ...params, displayCurrency }] as const;

/**
 * Backend conversion: header'daki `selected` currency `?displayCurrency`
 * query param olarak geçer; backend `displayPrice/displayCurrency/
 * exchangeRate` alanlarını doldurur. Currency değişince query key
 * değişir → otomatik refetch.
 */
export function useProductList(params: ListProductsParams = {}) {
  const displayCurrency = useCurrencyStore((s) => s.selected);

  return useQuery({
    queryKey: PRODUCT_LIST_QUERY_KEY(params, displayCurrency),
    queryFn: async () => {
      const search = new URLSearchParams();
      if (params.page !== undefined) search.set('page', String(params.page));
      if (params.pageSize !== undefined)
        search.set('pageSize', String(params.pageSize));
      if (params.search !== undefined && params.search !== '')
        search.set('search', params.search);
      search.set('displayCurrency', displayCurrency);

      const url = `/api/catalog/products?${search.toString()}`;

      const response = await clientFetch<
        ApiResponse<Product[]> & { meta: ProductListMeta }
      >(url);
      return { items: response.data, meta: response.meta };
    },
    staleTime: 60_000,
  });
}
