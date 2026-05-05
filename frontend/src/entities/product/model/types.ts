import type { CurrencyCode } from '@/entities/currency';

export interface Product {
  id: string;
  sku: string;
  name: string;
  description: string | null;
  price: number;
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
