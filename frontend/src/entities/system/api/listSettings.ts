'use client';

import { useQuery } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

export interface SystemSetting {
  key: string;
  value: string;
  type: 'string' | 'number' | 'boolean' | 'json';
  category: string;
  description: string | null;
  isPublic: boolean;
  updatedAt: string | null;
}

export const PUBLIC_SETTINGS_QUERY_KEY = ['system', 'settings', 'public'] as const;
export const ADMIN_SETTINGS_QUERY_KEY = ['system', 'settings', 'admin'] as const;

/** Public settings — boot anında frontend okur (base_currency vb.) */
export function usePublicSettings() {
  return useQuery({
    queryKey: PUBLIC_SETTINGS_QUERY_KEY,
    queryFn: async () => {
      const response = await clientFetch<ApiResponse<SystemSetting[]>>(
        '/api/system/settings/public',
      );
      return response.data;
    },
    staleTime: 5 * 60 * 1000,
  });
}

/** Admin yönetim sayfası için tüm ayarlar */
export function useAdminSettings() {
  return useQuery({
    queryKey: ADMIN_SETTINGS_QUERY_KEY,
    queryFn: async () => {
      const response = await clientFetch<ApiResponse<SystemSetting[]>>(
        '/api/admin/system/settings',
      );
      return response.data;
    },
  });
}
