'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';

import { CART_QUERY_KEY } from '@/entities/cart';
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
  /** ADR-017 — login sırasında guest cart user cart ile birleştirildi mi? */
  cartMerged: boolean;
}

/**
 * BFF /api/auth/login çağrısı. Proxy httpOnly cookie'leri kurar; biz
 * yalnızca /me cache'ini invalidate ediyoruz ki Header tazelensin.
 *
 * ADR-017: Guest cart cookie BFF tarafından login isteğine forward
 * edilir; backend MergeGuestCartCommand tetikler. Cevapta cartMerged
 * true ise URL'e ?merged=1 ekleyerek redirect — CartPage banner'ını
 * tetikler. Cart query'sini de invalidate ediyoruz ki yeni merge'lenmiş
 * sepet anında görünsün.
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
