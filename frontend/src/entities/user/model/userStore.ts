'use client';

import { create } from 'zustand';

import type { User } from './types';

/**
 * Lightweight user state — populated by the /me query in app shell
 * (entities/user/api/getCurrentUser). React Query owns the source of
 * truth; this store is the synchronously-readable mirror that widgets
 * (Header.UserMenu) and feature buttons can hit without subscribing
 * to the query themselves.
 */
interface UserStore {
  user: User | null;
  setUser: (user: User | null) => void;
}

export const useUserStore = create<UserStore>((set) => ({
  user: null,
  setUser: (user) => set({ user }),
}));
