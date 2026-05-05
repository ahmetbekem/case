'use client';

import { useRouter } from 'next/navigation';

import { useCurrentUser } from '@/entities/user';
import { ClientApiError } from '@/shared/api/client';
import { Button } from '@/shared/ui';

import { useAddItemMutation } from '../api/addItemMutation';

interface AddToCartButtonProps {
  productId: string;
  quantity?: number;
  disabled?: boolean;
  className?: string;
}

export function AddToCartButton({
  productId,
  quantity = 1,
  disabled = false,
  className,
}: AddToCartButtonProps) {
  const router = useRouter();
  const { data: user } = useCurrentUser();
  const mutation = useAddItemMutation();

  function onClick() {
    if (user === null || user === undefined) {
      router.push('/login');
      return;
    }
    mutation.mutate({ productId, quantity });
  }

  const isError = mutation.error instanceof ClientApiError;

  return (
    <div className={className}>
      <Button
        onClick={onClick}
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
