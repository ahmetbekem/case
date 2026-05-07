/**
 * ADR-018 — Backend AddressDTO mirror'u (camelCase). Backend phone hem
 * +E.164 hem display formatında döndürür; frontend display'i
 * formlarda göstermek için kullanır, gönderirken raw input.
 */
export interface Address {
  id: string;
  title: string;
  fullName: string;
  phone: string;
  phoneDisplay: string;
  countryCode: string;
  city: string;
  district: string;
  neighborhood: string | null;
  postalCode: string | null;
  addressLine: string;
  addressLine2: string | null;
  isDefaultDelivery: boolean;
  isDefaultBilling: boolean;
  createdAt: string;
  updatedAt: string | null;
}

export interface City {
  id: string;
  name: string;
}

export interface District {
  name: string;
}

export interface AddressFormPayload {
  title: string;
  fullName: string;
  phone: string;
  city: string;
  district: string;
  neighborhood?: string | null;
  postalCode?: string | null;
  addressLine: string;
  addressLine2?: string | null;
  setAsDefaultDelivery?: boolean;
  setAsDefaultBilling?: boolean;
}
