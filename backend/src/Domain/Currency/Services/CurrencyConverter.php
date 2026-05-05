<?php

declare(strict_types=1);

namespace Domain\Currency\Services;

use Domain\Currency\Entities\ExchangeRate;
use Domain\Currency\Exceptions\ExchangeRateNotFoundException;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;

/**
 * Pure conversion logic — no DB, no HTTP. Sales (PlaceOrderHandler)
 * loads the rate table once via ExchangeRateRepositoryInterface
 * ::loadAllLatest then hands it here so a single order computes its
 * display total without N round-trips per item.
 */
final readonly class CurrencyConverter
{
    /**
     * @param array<string, ExchangeRate> $rates Keyed by ExchangeRate::pairKey()
     */
    public function convert(Money $amount, Currency $target, array $rates): Money
    {
        if ($amount->currency === $target) {
            return $amount;
        }

        $key = $amount->currency->value . '-' . $target->value;

        if (!isset($rates[$key])) {
            throw ExchangeRateNotFoundException::forPair(
                $amount->currency->value,
                $target->value,
            );
        }

        $converted = $amount->amount * $rates[$key]->getRate();

        return new Money(round($converted, 2), $target);
    }
}
