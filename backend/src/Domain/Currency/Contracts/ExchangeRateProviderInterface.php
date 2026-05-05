<?php

declare(strict_types=1);

namespace Domain\Currency\Contracts;

use Domain\Shared\ValueObjects\Currency;

/**
 * Adapter contract for an external rate source (Frankfurter today,
 * could swap to TCMB / exchangerate-api.com without touching handlers).
 */
interface ExchangeRateProviderInterface
{
    /**
     * @param array<int, Currency> $targets
     * @return array<string, float> currencyCode => rate (1 base = N target)
     */
    public function fetchLatest(Currency $base, array $targets): array;
}
