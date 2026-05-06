<?php

declare(strict_types=1);

namespace Domain\System\Services;

use Domain\Shared\ValueObjects\Currency;
use Domain\System\Contracts\SettingRepositoryInterface;
use Domain\System\ValueObjects\SettingKey;

/**
 * Tip-güvenli ayar okuma servisi. Diğer modüller (Catalog, Sales)
 * doğrudan `SettingRepositoryInterface`'i kullanmak yerine bu servisi
 * tüketir — Currency enum cast'i, bilinmeyen değer fallback'i ve
 * default'lar tek yerde tutulur.
 *
 * Cache: ayrı bir CachedSettingResolver decorator'ı YOK. Repository
 * zaten CachedSettingRepository ile sarılmış (5 dk TTL); resolver
 * üstünde ikinci cache layer redundant olurdu. Ayar değişince
 * repository.save() cache'i invalidate eder; resolver da o noktada
 * fresh okur.
 */
final readonly class SettingResolver
{
    public function __construct(
        private SettingRepositoryInterface $repository,
    ) {
    }

    public function getBaseCurrency(): Currency
    {
        $raw = $this->repository->findByKey(SettingKey::BASE_CURRENCY)?->getValue() ?? 'TRY';
        return Currency::tryFrom($raw) ?? Currency::TRY;
    }

    public function getDefaultDisplayCurrency(): Currency
    {
        $raw = $this->repository->findByKey(SettingKey::DEFAULT_DISPLAY_CURRENCY)?->getValue() ?? 'TRY';
        return Currency::tryFrom($raw) ?? Currency::TRY;
    }

    /**
     * @return array<int, Currency>
     */
    public function getSupportedCurrencies(): array
    {
        $setting = $this->repository->findByKey(SettingKey::SUPPORTED_CURRENCIES);
        if ($setting === null) {
            // Default — DB'de henüz seed yoksa enum tüm cases
            return Currency::cases();
        }
        $codes = json_decode($setting->getValue(), true);
        if (!is_array($codes)) {
            return Currency::cases();
        }
        $result = [];
        foreach ($codes as $code) {
            if (!is_string($code)) {
                continue;
            }
            $currency = Currency::tryFrom($code);
            if ($currency !== null) {
                $result[] = $currency;
            }
        }
        return $result === [] ? Currency::cases() : $result;
    }
}
