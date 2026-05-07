'use client';

import type { Address } from '@/entities/address';
import { cn } from '@/shared/lib/cn';

interface AddressSelectorProps {
  addresses: Address[];
  selectedId: string | null;
  onSelect: (id: string) => void;
  /** "default delivery" mı yoksa "default billing" mi badge'leyelim. */
  highlight?: 'delivery' | 'billing';
  /** Boş listede gösterilecek metin. */
  emptyText?: string;
}

/**
 * ADR-018 — Checkout sayfası kayıtlı adres seçimi için minimal radio
 * card listesi. Default flag rozetleri label, accessible focus
 * ring'leri için sr-only input + label trick.
 */
export function AddressSelector({
  addresses,
  selectedId,
  onSelect,
  highlight = 'delivery',
  emptyText = 'Henüz adres yok.',
}: AddressSelectorProps) {
  if (addresses.length === 0) {
    return (
      <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        {emptyText}
      </p>
    );
  }

  return (
    <div className="space-y-2">
      {addresses.map((address) => {
        const isDefaultBadge =
          highlight === 'delivery' ? address.isDefaultDelivery : address.isDefaultBilling;
        return (
          <label
            key={address.id}
            className={cn(
              'block cursor-pointer rounded-lg border p-3 transition',
              selectedId === address.id
                ? 'border-slate-900 bg-slate-50 ring-1 ring-slate-900'
                : 'border-slate-200 hover:border-slate-400',
            )}
          >
            <input
              type="radio"
              value={address.id}
              checked={selectedId === address.id}
              onChange={() => onSelect(address.id)}
              className="sr-only"
            />
            <div className="flex items-start justify-between gap-3">
              <div className="space-y-0.5">
                <p className="font-semibold text-slate-900">{address.title}</p>
                <p className="text-xs text-slate-700">
                  {address.fullName} · {address.phoneDisplay}
                </p>
                <p className="text-xs text-slate-600">
                  {address.addressLine}
                  {address.addressLine2 ? `, ${address.addressLine2}` : ''}
                </p>
                <p className="text-xs text-slate-500">
                  {address.neighborhood ? `${address.neighborhood}, ` : ''}
                  {address.district} / {address.city}
                  {address.postalCode ? ` · ${address.postalCode}` : ''}
                </p>
              </div>
              {isDefaultBadge && (
                <span className="shrink-0 rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[10px] font-medium uppercase text-emerald-800">
                  Varsayılan
                </span>
              )}
            </div>
          </label>
        );
      })}
    </div>
  );
}
