<?php

declare(strict_types=1);

namespace Application\Sales\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Sales\Entities\OrderItem;

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
