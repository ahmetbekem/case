import { CheckoutForm } from '@/features/checkout-place-order';

export function CheckoutPage() {
  return (
    <div className="mx-auto max-w-5xl space-y-6 px-4 py-8">
      <h1 className="text-2xl font-bold tracking-tight text-slate-900">
        Ödeme
      </h1>
      <CheckoutForm />
    </div>
  );
}
