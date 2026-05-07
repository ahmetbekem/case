'use client';

import { Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';

import {
  type Address,
  type AddressFormPayload,
  useAddresses,
  useCreateAddressMutation,
  useDeleteAddressMutation,
  useSetDefaultBillingMutation,
  useSetDefaultDeliveryMutation,
  useUpdateAddressMutation,
} from '@/entities/address';
import { useCurrentUser } from '@/entities/user';
import { AddressForm, type AddressFormValues } from '@/features/address-form';
import { ClientApiError } from '@/shared/api/client';
import { confirmDialog, errorAlert, successAlert } from '@/shared/lib/alert';
import { Button, Card, CardContent, CardHeader, CardTitle } from '@/shared/ui';

/**
 * ADR-018 — Adreslerim sayfası. Liste + inline yarat / düzenle / sil
 * + default işaretleme. Auth zorunlu (sayfa-level guard).
 */
export function ProfileAddressesPage() {
  const { data: user, isLoading: userLoading } = useCurrentUser();
  const { data: addresses, isLoading, isError } = useAddresses();
  const createMutation = useCreateAddressMutation();
  const updateMutation = useUpdateAddressMutation();
  const deleteMutation = useDeleteAddressMutation();
  const setDefaultDelivery = useSetDefaultDeliveryMutation();
  const setDefaultBilling = useSetDefaultBillingMutation();

  const [mode, setMode] = useState<{ kind: 'list' } | { kind: 'create' } | { kind: 'edit'; id: string }>({
    kind: 'list',
  });

  if (userLoading) {
    return <p className="px-4 py-12 text-sm text-slate-500">Yükleniyor…</p>;
  }
  if (user === null || user === undefined) {
    return (
      <p className="mx-auto max-w-3xl px-4 py-12 text-sm text-slate-700">
        Adreslerinizi görmek için <a href="/login" className="font-medium underline">giriş yapın</a>.
      </p>
    );
  }
  if (isLoading) {
    return <p className="mx-auto max-w-4xl px-4 py-12 text-sm text-slate-500">Yükleniyor…</p>;
  }
  if (isError || addresses === undefined) {
    return (
      <p className="mx-auto max-w-4xl px-4 py-12 text-sm text-red-700">
        Adresler yüklenirken hata oluştu.
      </p>
    );
  }

  const editingAddress =
    mode.kind === 'edit' ? addresses.find((a) => a.id === mode.id) ?? null : null;

  async function handleCreate(payload: AddressFormPayload): Promise<void> {
    await createMutation.mutateAsync(payload);
    setMode({ kind: 'list' });
    void successAlert({ title: 'Adres eklendi', text: payload.title });
  }

  async function handleUpdate(id: string, payload: AddressFormPayload): Promise<void> {
    await updateMutation.mutateAsync({ id, payload });
    setMode({ kind: 'list' });
    void successAlert({ title: 'Adres güncellendi', text: payload.title });
  }

  async function handleDelete(address: Address): Promise<void> {
    const ok = await confirmDialog({
      title: 'Adresi silmek istediğinden emin misin?',
      html:
        `<p><strong>${escapeHtml(address.title)}</strong> adresi silinecek.</p>` +
        '<p style="margin-top:0.5rem">Önceki siparişlerinin adres bilgisi <em>snapshot olarak</em> korunur — sadece bu kayıt rehberinden çıkar.</p>',
      confirmButtonText: 'Evet, sil',
      cancelButtonText: 'Vazgeç',
      icon: 'warning',
    });
    if (!ok) return;
    try {
      await deleteMutation.mutateAsync(address.id);
      void successAlert({ title: 'Adres silindi' });
    } catch (err) {
      void errorAlert({
        title: 'Silinemedi',
        text: err instanceof ClientApiError ? err.message : 'Beklenmeyen hata',
      });
    }
  }

  async function handleSetDefault(address: Address, kind: 'delivery' | 'billing'): Promise<void> {
    try {
      if (kind === 'delivery') {
        await setDefaultDelivery.mutateAsync(address.id);
      } else {
        await setDefaultBilling.mutateAsync(address.id);
      }
      void successAlert({
        title: kind === 'delivery' ? 'Varsayılan teslimat ayarlandı' : 'Varsayılan fatura ayarlandı',
        text: address.title,
      });
    } catch (err) {
      void errorAlert({
        title: 'İşlem başarısız',
        text: err instanceof ClientApiError ? err.message : 'Beklenmeyen hata',
      });
    }
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6 px-4 py-8">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Adreslerim</h1>
          <p className="text-sm text-slate-500">
            Sipariş anında bu listeden seçim yapacaksın. En fazla 10 aktif adres
            tutabilirsin.
          </p>
        </div>
        {mode.kind === 'list' && (
          <Button onClick={() => setMode({ kind: 'create' })}>
            <Plus className="mr-1 h-4 w-4" />
            Yeni Adres
          </Button>
        )}
      </header>

      {mode.kind === 'create' && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Yeni Adres</CardTitle>
          </CardHeader>
          <CardContent>
            <AddressForm
              onSubmit={handleCreate}
              onCancel={() => setMode({ kind: 'list' })}
              submitLabel="Kaydet"
            />
          </CardContent>
        </Card>
      )}

      {mode.kind === 'edit' && editingAddress !== null && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Adresi Düzenle</CardTitle>
          </CardHeader>
          <CardContent>
            <AddressForm
              hideDefaultFlags
              initialValues={toFormValues(editingAddress)}
              onSubmit={(payload) => handleUpdate(editingAddress.id, payload)}
              onCancel={() => setMode({ kind: 'list' })}
              submitLabel="Güncelle"
            />
          </CardContent>
        </Card>
      )}

      {addresses.length === 0 && mode.kind === 'list' ? (
        <Card>
          <CardContent className="space-y-3 p-6 text-center">
            <p className="text-slate-700">
              Henüz adresin yok. Sipariş verebilmek için en az 1 adres eklemelisin.
            </p>
            <Button onClick={() => setMode({ kind: 'create' })}>
              <Plus className="mr-1 h-4 w-4" />
              İlk Adresi Ekle
            </Button>
          </CardContent>
        </Card>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {addresses.map((address) => (
            <AddressCard
              key={address.id}
              address={address}
              onEdit={() => setMode({ kind: 'edit', id: address.id })}
              onDelete={() => void handleDelete(address)}
              onSetDefaultDelivery={() => void handleSetDefault(address, 'delivery')}
              onSetDefaultBilling={() => void handleSetDefault(address, 'billing')}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function AddressCard({
  address,
  onEdit,
  onDelete,
  onSetDefaultDelivery,
  onSetDefaultBilling,
}: {
  address: Address;
  onEdit: () => void;
  onDelete: () => void;
  onSetDefaultDelivery: () => void;
  onSetDefaultBilling: () => void;
}) {
  return (
    <Card>
      <CardHeader className="flex-row items-start justify-between space-y-0">
        <div>
          <CardTitle className="text-base">{address.title}</CardTitle>
          <p className="text-xs text-slate-500">{address.fullName} · {address.phoneDisplay}</p>
        </div>
        <div className="flex flex-col items-end gap-1">
          {address.isDefaultDelivery && (
            <span className="rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[10px] font-medium uppercase text-emerald-800">
              Varsayılan teslimat
            </span>
          )}
          {address.isDefaultBilling && (
            <span className="rounded-full border border-blue-300 bg-blue-50 px-2 py-0.5 text-[10px] font-medium uppercase text-blue-800">
              Varsayılan fatura
            </span>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-3 text-sm">
        <p className="text-slate-700">{address.addressLine}</p>
        {address.addressLine2 && (
          <p className="text-xs text-slate-500">{address.addressLine2}</p>
        )}
        <p className="text-xs text-slate-500">
          {address.neighborhood ? `${address.neighborhood}, ` : ''}
          {address.district} / {address.city}
          {address.postalCode ? ` · ${address.postalCode}` : ''}
        </p>

        <div className="flex flex-wrap gap-2">
          <Button size="sm" variant="outline" onClick={onEdit}>
            <Pencil className="mr-1 h-3.5 w-3.5" />
            Düzenle
          </Button>
          {!address.isDefaultDelivery && (
            <Button size="sm" variant="ghost" onClick={onSetDefaultDelivery}>
              <Star className="mr-1 h-3.5 w-3.5" />
              Teslimat
            </Button>
          )}
          {!address.isDefaultBilling && (
            <Button size="sm" variant="ghost" onClick={onSetDefaultBilling}>
              <Star className="mr-1 h-3.5 w-3.5" />
              Fatura
            </Button>
          )}
          <Button size="sm" variant="ghost" onClick={onDelete}>
            <Trash2 className="mr-1 h-3.5 w-3.5" />
            Sil
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

function toFormValues(address: Address): Partial<AddressFormValues> {
  return {
    title: address.title,
    fullName: address.fullName,
    // Backend +E.164 döner; form regex hem +90 hem 0 hem raw kabul eder.
    phone: address.phone,
    city: address.city,
    district: address.district,
    neighborhood: address.neighborhood ?? '',
    postalCode: address.postalCode ?? '',
    addressLine: address.addressLine,
    addressLine2: address.addressLine2 ?? '',
  };
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
