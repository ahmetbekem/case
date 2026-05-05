<?php

declare(strict_types=1);

use Domain\Currency\Entities\ExchangeRate;
use Domain\Currency\Exceptions\ExchangeRateNotFoundException;
use Domain\Currency\Services\CurrencyConverter;
use Domain\Currency\ValueObjects\ExchangeRateId;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;

function makeRate(Currency $base, Currency $target, float $rate): ExchangeRate
{
    return new ExchangeRate(
        id: ExchangeRateId::generate(),
        baseCurrency: $base,
        targetCurrency: $target,
        rate: $rate,
        rateDate: new \DateTimeImmutable('today'),
        fetchedAt: new \DateTimeImmutable(),
    );
}

/**
 * @param array<int, ExchangeRate> $rates
 * @return array<string, ExchangeRate>
 */
function rateTable(array $rates): array
{
    $map = [];
    foreach ($rates as $r) {
        $map[$r->pairKey()] = $r;
    }
    return $map;
}

describe('CurrencyConverter', function () {
    beforeEach(function () {
        $this->converter = new CurrencyConverter();
    });

    it('returns the same Money when source equals target', function () {
        $money = new Money(100.0, Currency::TRY);

        $result = $this->converter->convert($money, Currency::TRY, []);

        expect($result)->toBe($money);
    });

    it('converts using the matching pair', function () {
        $rates = rateTable([makeRate(Currency::TRY, Currency::USD, 0.034)]);

        $result = $this->converter->convert(new Money(100.0, Currency::TRY), Currency::USD, $rates);

        expect($result->amount)->toBe(3.40)
            ->and($result->currency)->toBe(Currency::USD);
    });

    it('rounds to 2 decimals', function () {
        $rates = rateTable([makeRate(Currency::TRY, Currency::USD, 0.0339)]);

        $result = $this->converter->convert(new Money(100.0, Currency::TRY), Currency::USD, $rates);

        // 100 * 0.0339 = 3.39 (already 2dp); 50 * 0.0339 = 1.695 → rounds to 1.70
        expect($result->amount)->toBe(3.39);

        $result2 = $this->converter->convert(new Money(50.0, Currency::TRY), Currency::USD, $rates);
        expect($result2->amount)->toBe(1.70);
    });

    it('throws when the pair is missing from the rate table', function () {
        $rates = rateTable([makeRate(Currency::TRY, Currency::USD, 0.034)]);

        $this->converter->convert(new Money(100.0, Currency::TRY), Currency::EUR, $rates);
    })->throws(ExchangeRateNotFoundException::class);

    it('throws when the rate table is empty', function () {
        $this->converter->convert(new Money(100.0, Currency::TRY), Currency::USD, []);
    })->throws(ExchangeRateNotFoundException::class);
});
