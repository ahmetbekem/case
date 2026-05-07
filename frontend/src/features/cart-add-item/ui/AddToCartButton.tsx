'use client';

import { ClientApiError } from '@/shared/api/client';
import { Button } from '@/shared/ui';

import { useAddItemMutation } from '../api/addItemMutation';

interface AddToCartButtonProps {
  productId: string;
  quantity?: number;
  disabled?: boolean;
  className?: string;
}

/**
 * ADR-017 — auth check yok. Backend authenticated user için user cart'a,
 * guest için cookie session'lı guest cart'a ekler. Login akışı mağaza
 * checkout'unda devreye giriyor.
 */
export function AddToCartButton({
  productId,
  quantity = 1,
  disabled = false,
  className,
}: AddToCartButtonProps) {
  const mutation = useAddItemMutation();

  const isError = mutation.error instanceof ClientApiError;

  return (
    <div className={className}>
      <Button
        onClick={() => mutation.mutate({ productId, quantity })}
        disabled={disabled || mutation.isPending}
        className="w-full"
      >
        {mutation.isPending ? 'Ekleniyor…' : 'Sepete Ekle'}
      </Button>
      {isError && (
        <p className="mt-1 text-xs text-red-600">{(mutation.error as ClientApiError).message}</p>
      )}
      {mutation.isSuccess && (
        <p className="mt-1 text-xs text-emerald-700">Sepete eklendi</p>
      )}
    </div>
  );
}
