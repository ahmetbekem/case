<?php

declare(strict_types=1);

namespace Application\Currency\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Currency\Entities\ExchangeRate;

final readonly class ExchangeRateDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $base,
        public string $target,
        public float $rate,
        public string $rateDate,
        public string $fetchedAt,
        public string $provider,
    ) {
    }

    public static function fromEntity(ExchangeRate $rate): self
    {
        return new self(
            base: $rate->getBaseCurrency()->value,
            target: $rate->getTargetCurrency()->value,
            rate: $rate->getRate(),
            rateDate: $rate->getRateDate()->format('Y-m-d'),
            fetchedAt: $rate->getFetchedAt()->format(\DateTimeInterface::ATOM),
            provider: $rate->getProvider(),
        );
    }

    /**
     * Identity rate — base == target. Useful response for /currencies/TRY/TRY
     * so callers don't need a special-case before display.
     */
    public static function identity(string $code): self
    {
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        return new self(
            base: $code,
            target: $code,
            rate: 1.0,
            rateDate: (new \DateTimeImmutable())->format('Y-m-d'),
            fetchedAt: $now,
            provider: 'identity',
        );
    }

    /**
     * @param array<int, ExchangeRate> $rates
     * @return array<int, self>
     */
    public static function collection(array $rates): array
    {
        return array_map(fn (ExchangeRate $r) => self::fromEntity($r), $rates);
    }
}
