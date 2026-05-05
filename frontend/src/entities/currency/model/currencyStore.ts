'use client';

import { create } from 'zustand';
import { persist } from 'zustand/middleware';

import type { CurrencyCode } from './types';

/**
 * The currency the customer is viewing prices in. Persists in
 * localStorage so a refresh keeps their choice. Conversion math itself
 * lives in the backend (Currency module + Sales::PlaceOrderHandler);
 * this store only tracks which symbol/rate to display.
 */
interface CurrencyStore {
  selected: CurrencyCode;
  setSelected: (code: CurrencyCode) => void;
}

export const useCurrencyStore = create<CurrencyStore>()(
  persist(
    (set) => ({
      selected: 'TRY',
      setSelected: (selected) => set({ selected }),
    }),
    { name: 'dehasoft.currency' },
  ),
);
