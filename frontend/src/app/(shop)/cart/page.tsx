import { Suspense } from 'react';

import { CartPage } from '@/pages-ui/cart';

export const metadata = {
  title: 'Sepetim | Dehasoft',
};

export default function Page() {
  // CartPage useSearchParams (?merged=1 banner) kullanıyor; static
  // prerender için Suspense şart.
  return (
    <Suspense fallback={null}>
      <CartPage />
    </Suspense>
  );
}
