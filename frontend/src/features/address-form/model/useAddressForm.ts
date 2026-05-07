'use client';

import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

/**
 * ADR-018 — Address create/update form schema. Backend sıkı validate
 * eder (PhoneNumber VO normalize, TurkeyLocationsService il/ilçe);
 * frontend hızlı feedback için aynı kuralları lokal de uyguluyor.
 */
export const addressSchema = z.object({
  title: z
    .string()
    .min(2, 'Başlık en az 2 karakter olmalı')
    .max(50, 'Başlık çok uzun'),
  fullName: z
    .string()
    .min(3, 'Ad soyad en az 3 karakter olmalı')
    .max(255, 'Ad soyad çok uzun'),
  phone: z
    .string()
    .regex(/^\+?[0-9 \-()]{10,20}$/, 'Geçerli bir telefon numarası girin'),
  city: z.string().min(1, 'İl seçin'),
  district: z.string().min(1, 'İlçe seçin'),
  neighborhood: z.string().max(150, 'Mahalle çok uzun').optional().or(z.literal('')),
  postalCode: z
    .string()
    .regex(/^[0-9]{5}$/, 'Posta kodu 5 haneli olmalı')
    .optional()
    .or(z.literal('')),
  addressLine: z
    .string()
    .min(10, 'Açık adres en az 10 karakter olmalı')
    .max(500, 'Açık adres çok uzun'),
  addressLine2: z.string().max(255, 'Çok uzun').optional().or(z.literal('')),
  setAsDefaultDelivery: z.boolean().optional(),
  setAsDefaultBilling: z.boolean().optional(),
});

export type AddressFormValues = z.infer<typeof addressSchema>;

export function useAddressForm(initial?: Partial<AddressFormValues>) {
  return useForm<AddressFormValues>({
    resolver: zodResolver(addressSchema),
    defaultValues: {
      title: '',
      fullName: '',
      phone: '',
      city: '',
      district: '',
      neighborhood: '',
      postalCode: '',
      addressLine: '',
      addressLine2: '',
      setAsDefaultDelivery: false,
      setAsDefaultBilling: false,
      ...initial,
    },
  });
}
