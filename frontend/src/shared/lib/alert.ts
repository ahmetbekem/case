'use client';

import Swal, { type SweetAlertIcon, type SweetAlertResult } from 'sweetalert2';

/**
 * Sistem-genelinde tek SweetAlert wrapper'ı. UI tutarlılığı için tüm
 * confirmation/success/error modal'ları buradan çağrılır.
 *
 * - Türkçe default button label'ları
 * - Tailwind paletine yakın slate renkler (custom theming için
 *   `customClass` ile shadcn Button class'larını kullanmıyoruz; SwAl
 *   kendi DOM'unu basıyor, override yerine sade default'lar)
 * - SSR-safe: Swal yalnızca tarayıcıda execute edildiği için tüm
 *   helper'lar 'use client' modülünde tutulur ve sadece event
 *   handler'larda çağrılır.
 */

interface ConfirmOptions {
  title: string;
  text?: string;
  /** HTML — text yerine kullanılırsa zengin içerik gösterir. Caller'ın
   *  XSS güvenliğini garanti etmesi şart (sabit string'ler için OK). */
  html?: string;
  confirmButtonText?: string;
  cancelButtonText?: string;
  icon?: SweetAlertIcon;
}

export async function confirmDialog(options: ConfirmOptions): Promise<boolean> {
  const result: SweetAlertResult = await Swal.fire({
    title: options.title,
    text: options.text,
    html: options.html,
    icon: options.icon ?? 'warning',
    showCancelButton: true,
    confirmButtonText: options.confirmButtonText ?? 'Evet, devam et',
    cancelButtonText: options.cancelButtonText ?? 'Vazgeç',
    confirmButtonColor: '#0f172a', // slate-900
    cancelButtonColor: '#94a3b8', // slate-400
    reverseButtons: true,
    focusCancel: true,
  });
  return result.isConfirmed;
}

interface NotifyOptions {
  title: string;
  text?: string;
  html?: string;
}

export async function successAlert(options: NotifyOptions): Promise<void> {
  await Swal.fire({
    title: options.title,
    text: options.text,
    html: options.html,
    icon: 'success',
    confirmButtonText: 'Tamam',
    confirmButtonColor: '#0f172a',
  });
}

export async function errorAlert(options: NotifyOptions): Promise<void> {
  await Swal.fire({
    title: options.title,
    text: options.text,
    html: options.html,
    icon: 'error',
    confirmButtonText: 'Tamam',
    confirmButtonColor: '#dc2626', // red-600
  });
}

export async function infoAlert(options: NotifyOptions): Promise<void> {
  await Swal.fire({
    title: options.title,
    text: options.text,
    html: options.html,
    icon: 'info',
    confirmButtonText: 'Tamam',
    confirmButtonColor: '#0f172a',
  });
}
