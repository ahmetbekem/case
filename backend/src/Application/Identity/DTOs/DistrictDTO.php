<?php

declare(strict_types=1);

namespace Application\Identity\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DistrictDTO',
    title: 'District',
    description: 'Bir ilin ilçesi — ADR-018 statik veri.',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Kadıköy'),
    ],
    type: 'object',
)]
final readonly class DistrictDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $name,
    ) {
    }
}
