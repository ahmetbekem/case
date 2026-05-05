<?php

declare(strict_types=1);

namespace Application\Currency\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Currency\Entities\ExchangeRate;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ExchangeRateDTO',
    title: 'Exchange Rate',
    description: 'İki para birimi arasındaki dönüşüm kuru. Frankfurter API\'den '
        . 'Redis cache\'e çekilir; identity case için provider="identity" döner '
        . '(rate=1.0, base==target).',
    properties: [
        new OA\Property(property: 'base', type: 'string', enum: ['TRY', 'USD', 'EUR'], example: 'TRY'),
        new OA\Property(property: 'target', type: 'string', enum: ['TRY', 'USD', 'EUR'], example: 'USD'),
        new OA\Property(property: 'rate', type: 'number', format: 'float', description: '1 base = N target', example: 0.0307),
        new OA\Property(property: 'rateDate', type: 'string', format: 'date', description: 'Kurun ait olduğu tarih (Y-m-d)', example: '2026-05-05'),
        new OA\Property(property: 'fetchedAt', type: 'string', format: 'date-time', description: 'Kurun cache\'e yazılma anı'),
        new OA\Property(property: 'provider', type: 'string', example: 'frankfurter'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SupportedCurrencyDTO',
    title: 'Supported Currency',
    description: 'GET /currencies cevap satırı — Currency enum\'undan türeyen sabit liste.',
    properties: [
        new OA\Property(property: 'code', type: 'string', enum: ['TRY', 'USD', 'EUR'], example: 'TRY'),
        new OA\Property(property: 'symbol', type: 'string', example: '₺'),
    ],
    type: 'object',
)]
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
