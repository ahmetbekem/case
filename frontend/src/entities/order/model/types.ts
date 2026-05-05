import type { CurrencyCode } from '@/entities/currency';

export type OrderStatus = 'pending' | 'completed' | 'cancelled';

export interface OrderItem {
  id: string;
  productId: string;
  productName: string;
  productSku: string;
  quantity: number;
  unitPrice: number;
  currency: CurrencyCode;
  lineTotal: number;
  createdAt: string;
}

export interface Order {
  id: string;
  orderNumber: string;
  userId: string;
  status: OrderStatus;
  subtotal: number;
  total: number;
  currency: CurrencyCode;
  displayCurrency: CurrencyCode;
  displayTotal: number;
  exchangeRateSnapshot: number;
  notes: string | null;
  items: OrderItem[];
  itemCount: number;
  cancelledAt: string | null;
  cancelledReason: string | null;
  completedAt: string | null;
  createdAt: string;
  updatedAt: string | null;
}

export interface OrderListMeta {
  page: number;
  pageSize: number;
  total: number;
  totalPages: number;
}
