<?php

declare(strict_types=1);

namespace Domain\Currency\Contracts;

use Domain\Currency\Entities\ExchangeRate;
use Domain\Currency\ValueObjects\ExchangeRateId;
use Domain\Shared\Contracts\RepositoryInterface;
use Domain\Shared\ValueObjects\Currency;

/**
 * @extends RepositoryInterface<ExchangeRate, ExchangeRateId>
 */
interface ExchangeRateRepositoryInterface extends RepositoryInterface
{
    /**
     * Latest rate for the (base, target) pair. Hits the
     * idx_exchange_rates_pair_date_desc composite index.
     */
    public function findLatest(Currency $base, Currency $target): ?ExchangeRate;

    /**
     * All latest rates with the given base — handy for loading the entire
     * conversion table in one query before passing to CurrencyConverter.
     *
     * @return array<int, ExchangeRate>
     */
    public function loadAllLatest(Currency $base): array;

    /**
     * Idempotent insert keyed by (base, target, rate_date) — re-running
     * the daily sync over an existing day overwrites the rate cleanly.
     */
    public function upsert(ExchangeRate $rate): void;
}
