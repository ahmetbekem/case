'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';

import { CURRENT_USER_QUERY_KEY } from '@/entities/user';
import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { RegisterFormValues } from '../model/useRegisterForm';

interface RegisterResponse {
  user: {
    id: string;
    email: string;
    fullName: string;
    role: 'customer' | 'admin';
  };
}

export function useRegisterMutation(redirectTo = '/products') {
  const router = useRouter();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (values: RegisterFormValues) =>
      clientFetch<ApiResponse<RegisterResponse>>('/api/auth/register', {
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
