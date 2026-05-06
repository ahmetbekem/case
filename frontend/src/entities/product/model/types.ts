import type { CurrencyCode } from '@/entities/currency';

export interface Product {
  id: string;
  sku: string;
  name: string;
  description: string | null;
  /** ADR-016 — kanonik DB tutarı (base_currency'de) */
  basePrice: number;
  baseCurrency: CurrencyCode;
  /** Backend conversion sonucu — displayCurrency query'de verildiğinde dolu */
  displayPrice: number | null;
  displayCurrency: CurrencyCode | null;
  exchangeRate: number | null;
  /** @deprecated Legacy alias — basePrice ile aynı, ileride kaldırılacak */
  price: number;
  /** @deprecated Legacy alias — baseCurrency ile aynı */
  currency: CurrencyCode;
  stock: number;
  isActive: boolean;
  createdBy: string;
  createdAt: string;
  updatedAt: string | null;
}

export interface ProductListMeta {
  page: number;
  pageSize: number;
  total: number;
  totalPages: number;
}
