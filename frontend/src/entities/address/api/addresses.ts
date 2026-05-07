'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { Address, AddressFormPayload } from '../model/types';

export const ADDRESSES_QUERY_KEY = ['addresses', 'me'] as const;

export function useAddresses() {
  return useQuery({
    queryKey: ADDRESSES_QUERY_KEY,
    queryFn: async (): Promise<Address[]> => {
      const response = await clientFetch<ApiResponse<Address[]>>(
        '/api/identity/addresses',
      );
      return response.data;
    },
  });
}

export function useCreateAddressMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: AddressFormPayload): Promise<Address> => {
      const response = await clientFetch<ApiResponse<Address>>(
        '/api/identity/addresses',
        { method: 'POST', body: payload },
      );
      return response.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ADDRESSES_QUERY_KEY }),
  });
}

export function useUpdateAddressMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id: string;
      payload: AddressFormPayload;
    }): Promise<Address> => {
      const response = await clientFetch<ApiResponse<Address>>(
        `/api/identity/addresses/${id}`,
        { method: 'PUT', body: payload },
      );
      return response.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ADDRESSES_QUERY_KEY }),
  });
}

export function useDeleteAddressMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string): Promise<void> => {
      await clientFetch(`/api/identity/addresses/${id}`, { method: 'DELETE' });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ADDRESSES_QUERY_KEY }),
  });
}

export function useSetDefaultDeliveryMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string): Promise<Address> => {
      const response = await clientFetch<ApiResponse<Address>>(
        `/api/identity/addresses/${id}/default-delivery`,
        { method: 'PATCH' },
      );
      return response.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ADDRESSES_QUERY_KEY }),
  });
}

export function useSetDefaultBillingMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string): Promise<Address> => {
      const response = await clientFetch<ApiResponse<Address>>(
        `/api/identity/addresses/${id}/default-billing`,
        { method: 'PATCH' },
      );
      return response.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ADDRESSES_QUERY_KEY }),
  });
}
