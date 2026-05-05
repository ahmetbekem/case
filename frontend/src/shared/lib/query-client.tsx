'use client';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { type ReactNode, useState } from 'react';

/**
 * One QueryClient per browser tab — stored in useState so React keeps
 * the same instance across re-renders. Defaults are conservative:
 * - 60s staleTime so most reads hit the cache
 * - retry: 1 because the BFF already retries 401s with refresh once
 * - refetchOnWindowFocus: false to avoid surprise reloads
 */
export function QueryProvider({ children }: { children: ReactNode }) {
  const [client] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 60_000,
            retry: 1,
            refetchOnWindowFocus: false,
          },
          mutations: {
            retry: 0,
          },
        },
      }),
  );

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
