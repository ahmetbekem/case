'use client';

import { Check, X } from 'lucide-react';
import { useState } from 'react';

import {
  useAdminSettings,
  useUpdateSettingMutation,
  type SystemSetting,
} from '@/entities/system';
import { useCurrentUser } from '@/entities/user';
import { ClientApiError } from '@/shared/api/client';
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Input,
  Label,
} from '@/shared/ui';

/**
 * Sistem ayarlarını listeler + admin tek tek günceller. Yeni ayar
 * yaratma yok — seeder ile sabit set; admin yalnızca value'ları
 * değiştirir.
 *
 * UI: her ayar kart'ı, kategoriye göre gruplanmış. Inline edit (form
 * submission ile single mutation), no-op-on-same-value backend
 * tarafında zaten korunmuş (Setting::changeValue).
 */
export function AdminSettingsPage() {
  const { data: user, isLoading: userLoading } = useCurrentUser();
  const { data: settings, isLoading, isError } = useAdminSettings();
  const update = useUpdateSettingMutation();

  if (userLoading) {
    return <p className="px-4 py-12 text-sm text-slate-500">Yükleniyor…</p>;
  }
  if (user === null || user === undefined || user.role !== 'admin') {
    return (
      <p className="mx-auto max-w-3xl px-4 py-12 text-sm text-red-700">
        Bu sayfaya erişim için admin yetkisi gerekli.
      </p>
    );
  }
  if (isLoading) {
    return <p className="mx-auto max-w-4xl px-4 py-12 text-sm text-slate-500">Yükleniyor…</p>;
  }
  if (isError || settings === undefined) {
    return (
      <p className="mx-auto max-w-4xl px-4 py-12 text-sm text-red-700">
        Ayarlar yüklenirken hata oluştu.
      </p>
    );
  }

  // Kategoriye göre grupla
  const grouped = settings.reduce<Record<string, SystemSetting[]>>((acc, s) => {
    const cat = s.category || 'general';
    acc[cat] = acc[cat] ?? [];
    acc[cat].push(s);
    return acc;
  }, {});

  return (
    <div className="mx-auto max-w-4xl space-y-8 px-4 py-8">
      <header>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
          Sistem Ayarları
        </h1>
        <p className="text-sm text-slate-500">
          base_currency, supported_currencies vb. sistem-wide
          konfigürasyon. Değişiklikler 5 dk Redis cache invalidation
          sonrası tüm modüllere yansır.
        </p>
      </header>

      {Object.entries(grouped).map(([category, items]) => (
        <section key={category} className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
            {category}
          </h2>
          <div className="space-y-3">
            {items.map((setting) => (
              <SettingRow
                key={setting.key}
                setting={setting}
                onSave={(value) => update.mutateAsync({ key: setting.key, value })}
                isPending={update.isPending}
              />
            ))}
          </div>
        </section>
      ))}
    </div>
  );
}

function SettingRow({
  setting,
  onSave,
  isPending,
}: {
  setting: SystemSetting;
  onSave: (value: string) => Promise<unknown>;
  isPending: boolean;
}) {
  const [editing, setEditing] = useState(false);
  const [value, setValue] = useState(setting.value);
  const [error, setError] = useState<string | null>(null);

  function startEdit() {
    setValue(setting.value);
    setError(null);
    setEditing(true);
  }

  function cancel() {
    setEditing(false);
    setError(null);
  }

  async function save(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await onSave(value);
      setEditing(false);
    } catch (err) {
      setError(err instanceof ClientApiError ? err.message : 'Beklenmeyen hata');
    }
  }

  return (
    <Card>
      <CardHeader className="space-y-1">
        <div className="flex items-start justify-between gap-3">
          <CardTitle className="font-mono text-sm">{setting.key}</CardTitle>
          <div className="flex items-center gap-2">
            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium uppercase text-slate-700">
              {setting.type}
            </span>
            {setting.isPublic && (
              <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium uppercase text-emerald-900">
                public
              </span>
            )}
          </div>
        </div>
        {setting.description !== null && (
          <CardDescription className="text-xs">{setting.description}</CardDescription>
        )}
      </CardHeader>
      <CardContent>
        {!editing ? (
          <div className="flex items-center justify-between gap-3">
            <code className="break-all rounded bg-slate-50 px-2 py-1 text-sm text-slate-900">
              {setting.value}
            </code>
            <Button variant="outline" size="sm" onClick={startEdit}>
              Düzenle
            </Button>
          </div>
        ) : (
          <form onSubmit={save} className="space-y-2">
            <Label htmlFor={`val-${setting.key}`} className="text-xs">
              Yeni değer
            </Label>
            <Input
              id={`val-${setting.key}`}
              value={value}
              onChange={(e) => setValue(e.target.value)}
              maxLength={5000}
              required
            />
            {error !== null && (
              <p className="text-xs text-red-700">{error}</p>
            )}
            <div className="flex gap-2">
              <Button type="submit" size="sm" disabled={isPending}>
                <Check className="mr-1 h-4 w-4" />
                {isPending ? 'Kaydediliyor…' : 'Kaydet'}
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={cancel}
                disabled={isPending}
              >
                <X className="mr-1 h-4 w-4" />
                Vazgeç
              </Button>
            </div>
          </form>
        )}
      </CardContent>
    </Card>
  );
}
