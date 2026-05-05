'use client';

import { useQuery } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Currency } from '../model/types';

export const SUPPORTED_CURRENCIES_QUERY_KEY = ['currency', 'supported'] as const;

export function useSupportedCurrencies() {
  return useQuery({
    queryKey: SUPPORTED_CURRENCIES_QUERY_KEY,
    queryFn: async () => {
      const response = await clientFetch<ApiResponse<Currency[]>>(
        '/api/currencies',
      );
      return response.data;
    },
    staleTime: 24 * 60 * 60 * 1000, // 24h — currency list never changes mid-day
  });
}
