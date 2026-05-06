<?php

declare(strict_types=1);

namespace Application\System\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\System\Entities\Setting;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SettingDTO',
    title: 'System Setting',
    description: 'Tek bir sistem ayarı. value her zaman string; type alanı caller\'a parse hint verir.',
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'base_currency'),
        new OA\Property(property: 'value', type: 'string', description: 'Raw stringified değer (json type ise JSON-encoded)'),
        new OA\Property(property: 'type', type: 'string', enum: ['string', 'number', 'boolean', 'json']),
        new OA\Property(property: 'category', type: 'string', example: 'currency'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'isPublic', type: 'boolean'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final readonly class SettingDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $key,
        public string $value,
        public string $type,
        public string $category,
        public ?string $description,
        public bool $isPublic,
        public ?string $updatedAt,
    ) {
    }

    public static function fromEntity(Setting $s): self
    {
        return new self(
            key: $s->getKey(),
            value: $s->getValue(),
            type: $s->getType(),
            category: $s->getCategory(),
            description: $s->getDescription(),
            isPublic: $s->isPublic(),
            updatedAt: $s->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<int, Setting> $settings
     * @return array<int, self>
     */
    public static function collection(array $settings): array
    {
        return array_map(fn (Setting $s) => self::fromEntity($s), $settings);
    }
}
