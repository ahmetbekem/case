/**
 * Locale-aware formatters. UI components use these instead of hand-rolled
 * `toFixed(2)` / `Date.toString()` so price displays and timestamps stay
 * consistent across pages.
 */

const moneyFormatters = new Map<string, Intl.NumberFormat>();

function moneyFormatter(currency: string, locale: string): Intl.NumberFormat {
  const key = `${locale}|${currency}`;
  let cached = moneyFormatters.get(key);
  if (!cached) {
    cached = new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      maximumFractionDigits: 2,
    });
    moneyFormatters.set(key, cached);
  }
  return cached;
}

export function formatMoney(
  amount: number,
  currency: string,
  locale = 'tr-TR',
): string {
  return moneyFormatter(currency, locale).format(amount);
}

export function formatDate(
  iso: string | Date,
  locale = 'tr-TR',
  options: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  },
): string {
  const date = typeof iso === 'string' ? new Date(iso) : iso;
  return new Intl.DateTimeFormat(locale, options).format(date);
}

export function formatDateTime(iso: string | Date, locale = 'tr-TR'): string {
  return formatDate(iso, locale, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
