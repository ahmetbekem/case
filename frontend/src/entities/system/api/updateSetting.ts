'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import { ADMIN_SETTINGS_QUERY_KEY, PUBLIC_SETTINGS_QUERY_KEY } from './listSettings';

interface UpdateSettingPayload {
  key: string;
  value: string;
}

/**
 * ADR-016 — backend base_currency PATCH'i ChangeBaseCurrencyHandler'a
 * yönlendiriyor. Diğer key'ler SettingDTO döner; base_currency
 * ChangeBaseCurrencyResultDTO döner. Frontend response shape'i
 * discriminated union olarak modelliyor — caller başlık üzerinden
 * dallanır.
 */
export interface SettingUpdateResponseRegular {
  kind: 'setting';
  data: {
    key: string;
    value: string;
  };
  message: string;
}

export interface SettingUpdateResponseBaseCurrency {
  kind: 'base_currency';
  data: {
    fromCurrency: string;
    toCurrency: string;
    rate: number;
    rateDate: string;
    productsUpdated: number;
    cartsAbandoned: number;
  };
  message: string;
}

export type SettingUpdateResponse =
  | SettingUpdateResponseRegular
  | SettingUpdateResponseBaseCurrency;

export function useUpdateSettingMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ key, value }: UpdateSettingPayload): Promise<SettingUpdateResponse> => {
      const raw = await clientFetch<ApiResponse<Record<string, unknown>>>(
        `/api/admin/system/settings/${key}`,
        { method: 'PATCH', body: { value } },
      );
      // Discriminate by presence of fromCurrency in payload (only base
      // currency change handler returns it).
      if ('fromCurrency' in raw.data && 'toCurrency' in raw.data) {
        return {
          kind: 'base_currency',
          data: raw.data as SettingUpdateResponseBaseCurrency['data'],
          message: raw.message ?? 'Base currency changed',
        };
      }
      return {
        kind: 'setting',
        data: raw.data as SettingUpdateResponseRegular['data'],
        message: raw.message ?? 'Setting updated',
      };
    },
    onSuccess: async (response) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ADMIN_SETTINGS_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: PUBLIC_SETTINGS_QUERY_KEY }),
      ]);
      // ADR-016 — base_currency değişince product fiyatları + sepet
      // de değişti, ilgili cache'leri de tarat.
      if (response.kind === 'base_currency') {
        await Promise.all([
          queryClient.invalidateQueries({ queryKey: ['products'] }),
          queryClient.invalidateQueries({ queryKey: ['cart'] }),
        ]);
      }
    },
  });
}
