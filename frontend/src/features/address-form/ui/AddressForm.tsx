'use client';

import { useEffect } from 'react';

import { useCities, useDistricts, type AddressFormPayload } from '@/entities/address';
import { ClientApiError } from '@/shared/api/client';
import { Button, Input, Label } from '@/shared/ui';

import {
  addressSchema,
  useAddressForm,
  type AddressFormValues,
} from '../model/useAddressForm';

interface AddressFormProps {
  initialValues?: Partial<AddressFormValues>;
  /** Submit hook'u — başarı/başarısızlık caller'ın sorumluluğunda. */
  onSubmit: (payload: AddressFormPayload) => Promise<unknown>;
  onCancel?: () => void;
  submitLabel?: string;
  /** Default flag checkbox'larını gizle — checkout sayfasında inline yarat
   *  formunda bu seçenekler anlamsız (zaten orada seçeceğiz). */
  hideDefaultFlags?: boolean;
}

/**
 * ADR-018 — Adres ekleme/düzenleme formu.
 *
 * Kontrollü dropdown'lar: il seçilince ilçe listesi yeniden fetch'lenir;
 * il değişirse ilçe input'u sıfırlanır. useEffect setValue ile yönetilir
 * (rh-form ihmal etmez).
 */
export function AddressForm({
  initialValues,
  onSubmit,
  onCancel,
  submitLabel = 'Kaydet',
  hideDefaultFlags = false,
}: AddressFormProps) {
  const form = useAddressForm(initialValues);

  const selectedCity = form.watch('city');
  const { data: cities } = useCities();
  const { data: districts } = useDistricts(selectedCity || null);

  // Şehir değiştiyse ilçe seçimini temizle (dropdown stale değer
  // göstermesin); initialValues sırasında atlamak için
  // form.formState.dirtyFields kontrolü.
  const cityDirty = form.formState.dirtyFields.city === true;
  useEffect(() => {
    if (cityDirty) {
      form.setValue('district', '');
    }
  }, [selectedCity, cityDirty, form]);

  async function submit(values: AddressFormValues) {
    // Validation zaten zod tarafından geçti; payload'a dönüştürürken
    // boş string'leri null'a çevir (backend null bekler optional alanlarda).
    const payload: AddressFormPayload = {
      title: values.title,
      fullName: values.fullName,
      phone: values.phone,
      city: values.city,
      district: values.district,
      neighborhood: values.neighborhood && values.neighborhood !== '' ? values.neighborhood : null,
      postalCode: values.postalCode && values.postalCode !== '' ? values.postalCode : null,
      addressLine: values.addressLine,
      addressLine2: values.addressLine2 && values.addressLine2 !== '' ? values.addressLine2 : null,
      setAsDefaultDelivery: values.setAsDefaultDelivery,
      setAsDefaultBilling: values.setAsDefaultBilling,
    };
    try {
      await onSubmit(payload);
    } catch (err) {
      if (err instanceof ClientApiError) {
        // Tek input field error attach — backend errorCode INVALID_CITY/INVALID_DISTRICT
        // veya generic message ise form-level hata.
        form.setError('root', { message: err.message });
      } else {
        form.setError('root', { message: 'Beklenmeyen hata' });
      }
    }
  }

  return (
    <form onSubmit={form.handleSubmit(submit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="title">Adres Başlığı</Label>
          <Input id="title" placeholder="Ev, İş, Yazlık…" {...form.register('title')} />
          {form.formState.errors.title && (
            <p className="text-xs text-red-600">{form.formState.errors.title.message}</p>
          )}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="fullName">Ad Soyad</Label>
          <Input id="fullName" {...form.register('fullName')} />
          {form.formState.errors.fullName && (
            <p className="text-xs text-red-600">{form.formState.errors.fullName.message}</p>
          )}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="phone">Telefon</Label>
        <Input id="phone" placeholder="+90 555 123 45 67" {...form.register('phone')} />
        {form.formState.errors.phone && (
          <p className="text-xs text-red-600">{form.formState.errors.phone.message}</p>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="city">İl</Label>
          <select
            id="city"
            {...form.register('city')}
            className="flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
          >
            <option value="">Seçin…</option>
            {cities?.map((c) => (
              <option key={c.id} value={c.name}>
                {c.name}
              </option>
            ))}
          </select>
          {form.formState.errors.city && (
            <p className="text-xs text-red-600">{form.formState.errors.city.message}</p>
          )}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="district">İlçe</Label>
          <select
            id="district"
            disabled={!selectedCity || (districts ?? []).length === 0}
            {...form.register('district')}
            className="flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100"
          >
            <option value="">{selectedCity ? 'Seçin…' : 'Önce il seçin'}</option>
            {districts?.map((d) => (
              <option key={d.name} value={d.name}>
                {d.name}
              </option>
            ))}
          </select>
          {form.formState.errors.district && (
            <p className="text-xs text-red-600">{form.formState.errors.district.message}</p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="neighborhood">Mahalle (opsiyonel)</Label>
          <Input id="neighborhood" {...form.register('neighborhood')} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="postalCode">Posta Kodu (opsiyonel)</Label>
          <Input id="postalCode" maxLength={5} placeholder="34710" {...form.register('postalCode')} />
          {form.formState.errors.postalCode && (
            <p className="text-xs text-red-600">{form.formState.errors.postalCode.message}</p>
          )}
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="addressLine">Açık Adres</Label>
        <textarea
          id="addressLine"
          rows={3}
          placeholder="Cadde, sokak, bina no, daire…"
          className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900"
          {...form.register('addressLine')}
        />
        {form.formState.errors.addressLine && (
          <p className="text-xs text-red-600">{form.formState.errors.addressLine.message}</p>
        )}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="addressLine2">Apt / Daire / Kat (opsiyonel)</Label>
        <Input id="addressLine2" {...form.register('addressLine2')} />
      </div>

      {!hideDefaultFlags && (
        <div className="space-y-2 rounded-md border border-slate-200 p-3 text-sm">
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              {...form.register('setAsDefaultDelivery')}
              className="h-4 w-4 rounded border-slate-300"
            />
            <span>Varsayılan teslimat adresim olarak ayarla</span>
          </label>
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              {...form.register('setAsDefaultBilling')}
              className="h-4 w-4 rounded border-slate-300"
            />
            <span>Varsayılan fatura adresim olarak ayarla</span>
          </label>
        </div>
      )}

      {form.formState.errors.root && (
        <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {form.formState.errors.root.message}
        </p>
      )}

      <div className="flex gap-2">
        <Button type="submit" disabled={form.formState.isSubmitting}>
          {form.formState.isSubmitting ? 'Kaydediliyor…' : submitLabel}
        </Button>
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel} disabled={form.formState.isSubmitting}>
            Vazgeç
          </Button>
        )}
      </div>
    </form>
  );
}

export { addressSchema };
