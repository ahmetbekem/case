'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';

import { CURRENT_USER_QUERY_KEY } from '@/entities/user';
import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { LoginFormValues } from '../model/useLoginForm';

interface LoginResponse {
  user: {
    id: string;
    email: string;
    fullName: string;
    role: 'customer' | 'admin';
  };
}

/**
 * Calls the BFF /api/auth/login. The proxy installs the httpOnly
 * cookies; we just need to invalidate the cached /me query so the
 * Header repaints with the freshly logged-in user.
 */
export function useLoginMutation(redirectTo = '/products') {
  const router = useRouter();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (values: LoginFormValues) =>
      clientFetch<ApiResponse<LoginResponse>>('/api/auth/login', {
        method: 'POST',
        body: values,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: CURRENT_USER_QUERY_KEY });
      router.push(redirectTo);
      router.refresh();
    },
  });
}
