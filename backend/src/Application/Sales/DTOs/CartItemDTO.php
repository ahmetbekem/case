<?php

declare(strict_types=1);

namespace Application\Sales\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Sales\Entities\CartItem;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CartItemDTO',
    title: 'Cart Item',
    description: 'Sepetteki tek satır — fiyat ekleme anındaki snapshot.',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'productId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'quantity', type: 'integer', example: 2),
        new OA\Property(property: 'unitPrice', type: 'number', format: 'float', example: 100.00),
        new OA\Property(property: 'currency', type: 'string', enum: ['TRY', 'USD', 'EUR']),
        new OA\Property(property: 'lineTotal', type: 'number', format: 'float', example: 200.00),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final readonly class CartItemDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $id,
        public string $productId,
        public int $quantity,
        public float $unitPrice,
        public string $currency,
        public float $lineTotal,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public static function fromEntity(CartItem $item): self
    {
        return new self(
            id: $item->getId()->value(),
            productId: $item->getProductId(),
            quantity: $item->getQuantity(),
            unitPrice: $item->getUnitPriceSnapshot()->amount,
            currency: $item->getUnitPriceSnapshot()->currency->value,
            lineTotal: $item->lineTotal()->amount,
            createdAt: $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $item->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<int, CartItem> $items
     * @return array<int, self>
     */
    public static function collection(array $items): array
    {
        return array_map(fn (CartItem $i) => self::fromEntity($i), $items);
    }
}
