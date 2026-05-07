import type { CurrencyCode } from '@/entities/currency';

export interface CartItem {
  id: string;
  productId: string;
  productName: string;
  productSku: string;
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
  /** ADR-017 — guest cart için null */
  userId: string | null;
  /** ADR-017 — yalnızca guest cart için doludur */
  guestSessionId: string | null;
  /** ADR-017 — guestSessionId !== null kısa yolu */
  isGuest: boolean;
  status: CartStatus;
  convertedToOrderId: string | null;
  items: CartItem[];
  itemCount: number;
  totalQuantity: number;
  /** Primary currency (ilk item) toplamı — uniform cart'ta full subtotal */
  subtotal: number;
  currency: CurrencyCode;
  /** Her para birimi için ayrı subtotal — mixed cart için. {TRY: 758, USD: 49.9} */
  subtotalsByCurrency: Partial<Record<CurrencyCode, number>>;
  /** false ise checkout butonu disabled */
  isUniformCurrency: boolean;
  createdAt: string;
  updatedAt: string | null;
}
