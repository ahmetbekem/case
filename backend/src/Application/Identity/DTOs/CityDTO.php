<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CityDTO',
    title: 'City',
    description: 'Türkiye il listesi entry\'si — ADR-018 statik veri.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: '34'),
        new OA\Property(property: 'name', type: 'string', example: 'İstanbul'),
    ],
    type: 'object',
)]
final readonly class CityDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
