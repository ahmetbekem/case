<?php

declare(strict_types=1);

namespace Application\System\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ChangeBaseCurrencyResultDTO',
    title: 'Base Currency Change Result',
    description: 'Admin base_currency değiştirdikten sonra UI\'a geri dönen sonuç — '
        . 'kaç ürün repriceall edildi, kaç aktif cart abandoned\'a çekildi, kullanılan kur.',
    properties: [
        new OA\Property(property: 'fromCurrency', type: 'string', example: 'TRY'),
        new OA\Property(property: 'toCurrency', type: 'string', example: 'USD'),
        new OA\Property(property: 'rate', type: 'number', format: 'float', example: 0.029),
        new OA\Property(property: 'rateDate', type: 'string', format: 'date', example: '2026-05-06'),
        new OA\Property(property: 'productsUpdated', type: 'integer', example: 142),
        new OA\Property(property: 'cartsAbandoned', type: 'integer', example: 8),
    ],
    type: 'object',
)]
final readonly class ChangeBaseCurrencyResultDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $fromCurrency,
        public string $toCurrency,
        public float $rate,
        public string $rateDate,
        public int $productsUpdated,
        public int $cartsAbandoned,
    ) {
    }
}
