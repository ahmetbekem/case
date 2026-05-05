<?php

declare(strict_types=1);

namespace Application\Sales\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Sales\Entities\OrderItem;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderItemDTO',
    title: 'Order Item',
    description: 'Sipariş satırı — productName/SKU snapshot\'ı sipariş anındaki '
        . 'değer; ürün sonradan değişse bile burada dondurulmuş kalır.',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'productId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'productName', type: 'string', description: 'Snapshot — sipariş anındaki ad'),
        new OA\Property(property: 'productSku', type: 'string', description: 'Snapshot — sipariş anındaki SKU'),
        new OA\Property(property: 'quantity', type: 'integer'),
        new OA\Property(property: 'unitPrice', type: 'number', format: 'float'),
        new OA\Property(property: 'currency', type: 'string', enum: ['TRY', 'USD', 'EUR']),
        new OA\Property(property: 'lineTotal', type: 'number', format: 'float'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final readonly class OrderItemDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $productName,
        public string $productSku,
        public int $quantity,
        public float $unitPrice,
        public string $currency,
        public float $lineTotal,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(OrderItem $item): self
    {
        return new self(
            id: $item->getId()->value(),
            productId: $item->getProductId(),
            productName: $item->getProductNameSnapshot(),
            productSku: $item->getProductSkuSnapshot(),
            quantity: $item->getQuantity(),
            unitPrice: $item->getUnitPrice()->amount,
            currency: $item->getUnitPrice()->currency->value,
            lineTotal: $item->getLineTotal()->amount,
            createdAt: $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<int, OrderItem> $items
     * @return array<int, self>
     */
    public static function collection(array $items): array
    {
        return array_map(fn (OrderItem $i) => self::fromEntity($i), $items);
    }
}
