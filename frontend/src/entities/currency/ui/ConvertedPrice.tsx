'use client';

import { formatMoney } from '@/shared/lib/format';
import { cn } from '@/shared/lib/cn';

import { useExchangeRate } from '../api/getExchangeRate';
import { useCurrencyStore } from '../model/currencyStore';
import type { CurrencyCode } from '../model/types';

interface ConvertedPriceProps {
  amount: number;
  currency: CurrencyCode;
  /** Asıl tutar farklı para biriminde ise altında küçük "≈ original" satırı gösterilir */
  showOriginal?: boolean;
  className?: string;
  originalClassName?: string;
}

/**
 * Header'daki para birimi selector'ından gelen `selected` üzerinden
 * fiyatı dönüştürür. base === target ise direkt formatlanır;
 * aksi halde kur fetch eder ve dönüştürülmüş tutarı gösterir.
 *
 * Yükleniyor durumunda orijinal tutarı placeholder olarak gösterir
 * (UI atlamasını önler — flicker yok).
 */
export function ConvertedPrice({
  amount,
  currency,
  showOriginal = true,
  className,
  originalClassName,
}: ConvertedPriceProps) {
  const target = useCurrencyStore((s) => s.selected);
  const sameCurrency = currency === target;

  const { data: rate, isLoading, isError } = useExchangeRate(currency, target);

  // Identity case veya kur henüz yok → orijinal tutarı göster
  if (sameCurrency || rate === undefined || isError) {
    return <span className={className}>{formatMoney(amount, currency)}</span>;
  }

  const converted = amount * rate;

  return (
    <span className={cn('inline-flex flex-col items-end leading-tight', className)}>
      <span className={cn(isLoading && 'opacity-70')}>
        {formatMoney(converted, target)}
      </span>
      {showOriginal && (
        <span
          className={cn(
            'text-xs font-normal text-slate-500',
            originalClassName,
          )}
        >
          ≈ {formatMoney(amount, currency)}
        </span>
      )}
    </span>
  );
}
