'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';

import { CART_QUERY_KEY } from '@/entities/cart';
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
  /** ADR-017 — register sırasında guest cart user'a transfer edildi mi? */
  cartMerged: boolean;
}

/**
 * ADR-017 — login mutation'ı ile aynı pattern: cart query'sini invalidate
 * et, cartMerged true ise URL'e ?merged=1 ekle.
 */
export function useRegisterMutation(redirectTo = '/products') {
  const router = useRouter();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (values: RegisterFormValues) =>
      clientFetch<ApiResponse<RegisterResponse>>('/api/auth/register', {
        method: 'POST',
        body: values,
      }),
    onSuccess: async (response) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: CURRENT_USER_QUERY_KEY }),
        queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY }),
      ]);
      const target = response.data.cartMerged
        ? appendQueryParam(redirectTo, 'merged', '1')
        : redirectTo;
      router.push(target);
      router.refresh();
    },
  });
}

function appendQueryParam(url: string, key: string, value: string): string {
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}${key}=${encodeURIComponent(value)}`;
}
