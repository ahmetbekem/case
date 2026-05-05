import { cn } from '@/shared/lib/cn';

import type { OrderStatus } from '../model/types';

const statusStyles: Record<OrderStatus, string> = {
  pending: 'bg-amber-100 text-amber-900 ring-amber-300',
  completed: 'bg-emerald-100 text-emerald-900 ring-emerald-300',
  cancelled: 'bg-red-100 text-red-900 ring-red-300',
};

const statusLabels: Record<OrderStatus, string> = {
  pending: 'Beklemede',
  completed: 'Tamamlandı',
  cancelled: 'İptal',
};

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
        statusStyles[status],
      )}
    >
      {statusLabels[status]}
    </span>
  );
}
