export type CurrencyCode = 'TRY' | 'USD' | 'EUR';

export interface Currency {
  code: CurrencyCode;
  symbol: string;
}
