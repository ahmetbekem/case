<?php

declare(strict_types=1);

namespace Domain\System\ValueObjects;

/**
 * Bilinen ayar anahtarları — tip-güvenli sabit set. Yeni ayar eklemek
 * için: const ekle + SettingResolver'a getter (gerekiyorsa) + seeder
 * default value.
 *
 * Final readonly value-object yerine const-only sınıf — enum'a alternatif
 * çünkü string sabit dışı bir behaviour yok ve PHP enum'da const kullanım
 * sınırlaması var.
 */
final class SettingKey
{
    public const string BASE_CURRENCY = 'base_currency';
    public const string DEFAULT_DISPLAY_CURRENCY = 'default_display_currency';
    public const string SUPPORTED_CURRENCIES = 'supported_currencies';
    public const string SITE_NAME = 'site_name';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::BASE_CURRENCY,
            self::DEFAULT_DISPLAY_CURRENCY,
            self::SUPPORTED_CURRENCIES,
            self::SITE_NAME,
        ];
    }

    private function __construct()
    {
    }
}
