'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';

import { clientFetch } from '@/shared/api/client';

/**
 * Hits BFF /api/auth/logout — the proxy clears cookies regardless of
 * the backend result. We then drop every cached query so a returning
 * user (e.g. login as a different account) doesn't see stale data.
 */
export function useLogoutMutation() {
  const router = useRouter();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async () =>
      clientFetch('/api/auth/logout', { method: 'POST' }),
    onSettled: () => {
      queryClient.clear();
      router.push('/login');
      router.refresh();
    },
  });
}
