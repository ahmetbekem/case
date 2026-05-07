'use client';

import { useQuery } from '@tanstack/react-query';

import { clientFetch } from '@/shared/api/client';
import type { ApiResponse } from '@/shared/api/types';

import type { City, District } from '../model/types';

/**
 * ADR-018 — Statik veri; staleTime=Infinity (asla expire olmasın).
 * Sayfa reload'da fresh ama refetch yok.
 */
const STALE_FOREVER = Number.POSITIVE_INFINITY;

export const CITIES_QUERY_KEY = ['locations', 'cities'] as const;
export const DISTRICTS_QUERY_KEY = (city: string | null) =>
  ['locations', 'districts', city] as const;

export function useCities() {
  return useQuery({
    queryKey: CITIES_QUERY_KEY,
    queryFn: async (): Promise<City[]> => {
      const response = await clientFetch<ApiResponse<City[]>>('/api/locations/cities');
      return response.data;
    },
    staleTime: STALE_FOREVER,
  });
}

export function useDistricts(city: string | null) {
  return useQuery({
    queryKey: DISTRICTS_QUERY_KEY(city),
    queryFn: async (): Promise<District[]> => {
      const response = await clientFetch<ApiResponse<District[]>>(
        `/api/locations/cities/${encodeURIComponent(city as string)}/districts`,
      );
      return response.data;
    },
    enabled: city !== null && city !== '',
    staleTime: STALE_FOREVER,
  });
}
