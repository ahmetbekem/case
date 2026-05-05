'use client';

import { useQuery } from '@tanstack/react-query';

import { ClientApiError, clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { CurrencyCode } from '../model/types';

interface ExchangeRateResponse {
  base: CurrencyCode;
  target: CurrencyCode;
  rate: number;
  rateDate: string;
  fetchedAt: string;
  provider: string;
}

export const EXCHANGE_RATE_QUERY_KEY = (
  base: CurrencyCode,
  target: CurrencyCode,
) => ['currency', 'rate', base, target] as const;

async function fetchRate(
  base: CurrencyCode,
  target: CurrencyCode,
): Promise<number | null> {
  try {
    const r = await clientFetch<ApiResponse<ExchangeRateResponse>>(
      `/api/currencies/${base}/${target}`,
    );
    return r.data.rate;
  } catch (err) {
    if (err instanceof ClientApiError && err.status === 404) {
      return null;
    }
    throw err;
  }
}

/**
 * Tek bir base→target dönüşüm kuru.
 *
 * - base === target ise network'e çıkmaz, identity (1.0) döner.
 * - 5 dk staleTime — backend tarafı 6 saatte bir scheduler refresh
 *   ettiği için saniyelik tazelik gerekmiyor.
 * - **Inverse fallback:** Bazı kurlar tek yönlü stored (örn TRY→USD
 *   var ama USD→TRY yok — Frankfurter free tier base'e göre dönüyor
 *   ve sync handler yalnızca TRY base ile çekiyor). 404 alırsak
 *   target→base dene, sonucu 1/rate ile çevir. Bu sayede tüm 3×3 = 9
 *   kombinasyon UI tarafında çalışır.
 * - Aynı (base,target) için TanStack Query otomatik dedup yapar.
 */
export function useExchangeRate(base: CurrencyCode, target: CurrencyCode) {
  return useQuery({
    queryKey: EXCHANGE_RATE_QUERY_KEY(base, target),
    queryFn: async (): Promise<number> => {
      const direct = await fetchRate(base, target);
      if (direct !== null) return direct;

      const inverse = await fetchRate(target, base);
      if (inverse !== null && inverse !== 0) return 1 / inverse;

      throw new Error(`Exchange rate not available: ${base}->${target}`);
    },
    enabled: base !== target,
    staleTime: 5 * 60 * 1000,
    initialData: base === target ? 1 : undefined,
    retry: false,
  });
}
