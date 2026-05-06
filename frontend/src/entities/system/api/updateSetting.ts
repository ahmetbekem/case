'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';

import { ADMIN_SETTINGS_QUERY_KEY, PUBLIC_SETTINGS_QUERY_KEY } from './listSettings';

interface UpdateSettingPayload {
  key: string;
  value: string;
}

/**
 * Backend cache invalidation save() içinde yapılır (CachedSettingRepository
 * decorator). Frontend de admin + public query'leri invalidate eder ki
 * her iki yerden de fresh okusun.
 */
export function useUpdateSettingMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ key, value }: UpdateSettingPayload) =>
      clientFetch(`/api/admin/system/settings/${key}`, {
        method: 'PATCH',
        body: { value },
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ADMIN_SETTINGS_QUERY_KEY });
      await queryClient.invalidateQueries({ queryKey: PUBLIC_SETTINGS_QUERY_KEY });
    },
  });
}
