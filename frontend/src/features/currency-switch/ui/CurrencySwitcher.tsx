'use client';

import { useCurrencyStore, useSupportedCurrencies } from '@/entities/currency';
import { cn } from '@/shared/lib/cn';

interface CurrencySwitcherProps {
  className?: string;
}

export function CurrencySwitcher({ className }: CurrencySwitcherProps) {
  const selected = useCurrencyStore((s) => s.selected);
  const setSelected = useCurrencyStore((s) => s.setSelected);
  const { data: currencies, isLoading } = useSupportedCurrencies();

  // Backend hasn't responded yet — render a static placeholder with
  // the persisted selection so the header doesn't jump on hydrate.
  const options =
    currencies ??
    [
      { code: 'TRY' as const, symbol: '₺' },
      { code: 'USD' as const, symbol: '$' },
      { code: 'EUR' as const, symbol: '€' },
    ];

  return (
    <select
      value={selected}
      onChange={(event) =>
        setSelected(event.target.value as 'TRY' | 'USD' | 'EUR')
      }
      disabled={isLoading}
      className={cn(
        'h-9 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900',
        className,
      )}
      aria-label="Para birimi seç"
    >
      {options.map((c) => (
        <option key={c.code} value={c.code}>
          {c.symbol} {c.code}
        </option>
      ))}
    </select>
  );
}
