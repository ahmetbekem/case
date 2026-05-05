'use client';

import { useQuery } from '@tanstack/react-query';

import { ClientApiError, clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { User } from '../model/types';

export const CURRENT_USER_QUERY_KEY = ['user', 'me'] as const;

/**
 * /me — returns the logged-in user, or null when the BFF reports 401.
 * Other failures bubble; the consumer decides whether to render a
 * loading skeleton or a "try again" message.
 */
export function useCurrentUser() {
  return useQuery({
    queryKey: CURRENT_USER_QUERY_KEY,
    queryFn: async (): Promise<User | null> => {
      try {
        const response = await clientFetch<ApiResponse<User>>('/api/auth/me');
        return response.data;
      } catch (error) {
        if (error instanceof ClientApiError && error.status === 401) {
          return null;
        }
        throw error;
      }
    },
    retry: false,
    staleTime: 5 * 60_000,
  });
}
