<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObjects;

use Domain\Shared\Abstracts\AbstractValueObject;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\Exceptions\ValidationException;

/**
 * ADR-018 — TR telefon numarası. Constructor ham string'i E.164
 * formatına normalize ederek saklar (+905551234567); display
 * format frontend tarafının sorumluluğunda.
 *
 * Kabul edilen ham formatlar:
 *   - +90 555 123 45 67   (uluslararası)
 *   - 0 555 123 45 67     (yerel; 0 +90'a dönüşür)
 *   - 5551234567          (10 hane; +90 prefix eklenir)
 *   - +90(555) 123-45-67  (parens + dash)
 *
 * Kayıt sonrası tek format: +905551234567 (12 char). DB'deki phone
 * kolonunun chk_addresses_phone CHECK constraint'i bu set'e zaten
 * uyuyor.
 */
final readonly class PhoneNumber extends AbstractValueObject
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $normalized = self::normalizeRaw($raw);
        if ($normalized === null) {
            throw ValidationException::withMessage(
                'Invalid phone number format',
                ['phone' => $raw],
            );
        }
        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(ValueObjectInterface $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    /**
     * Display format için: +905551234567 → 0 555 123 45 67
     * Frontend bunu çağırabilir veya kendi formatlama yapabilir.
     */
    public function toDisplay(): string
    {
        // +905551234567 → 0 555 123 45 67
        if (str_starts_with($this->value, '+90') && strlen($this->value) === 13) {
            $local = '0' . substr($this->value, 3);
            return sprintf(
                '%s %s %s %s %s',
                substr($local, 0, 1),
                substr($local, 1, 3),
                substr($local, 4, 3),
                substr($local, 7, 2),
                substr($local, 9, 2),
            );
        }
        return $this->value;
    }

    /**
     * Ham input'u +E.164'e çevir; geçersizse null. TR-only şu an
     * (10 hane, mobile + landline destekler).
     */
    private static function normalizeRaw(string $raw): ?string
    {
        $cleaned = preg_replace('/[\s\-()]/', '', trim($raw));
        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        // +90 ile başlıyorsa olduğu gibi al
        if (str_starts_with($cleaned, '+90') && preg_match('/^\+90[0-9]{10}$/', $cleaned)) {
            return $cleaned;
        }
        // 0 ile başlıyorsa +90 ile değiştir (TR yerel)
        if (str_starts_with($cleaned, '0') && preg_match('/^0[0-9]{10}$/', $cleaned)) {
            return '+90' . substr($cleaned, 1);
        }
        // Yalnızca 10 hane ise direkt +90 prefix ekle
        if (preg_match('/^[0-9]{10}$/', $cleaned)) {
            return '+90' . $cleaned;
        }
        return null;
    }
}
