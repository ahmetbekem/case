import type { CurrencyCode } from '@/entities/currency';

export interface CartItem {
  id: string;
  productId: string;
  quantity: number;
  unitPrice: number;
  currency: CurrencyCode;
  lineTotal: number;
  createdAt: string;
  updatedAt: string | null;
}

export type CartStatus = 'active' | 'converted' | 'abandoned';

export interface Cart {
  id: string;
  userId: string;
  status: CartStatus;
  convertedToOrderId: string | null;
  items: CartItem[];
  itemCount: number;
  totalQuantity: number;
  subtotal: number;
  currency: CurrencyCode;
  createdAt: string;
  updatedAt: string | null;
}
