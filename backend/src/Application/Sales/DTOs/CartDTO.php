<?php

declare(strict_types=1);

namespace Application\Sales\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Sales\Entities\Cart;

final readonly class CartDTO extends AbstractOutputDTO
{
    /**
     * @param array<int, CartItemDTO> $items
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $status,
        public ?string $convertedToOrderId,
        public array $items,
        public int $itemCount,
        public int $totalQuantity,
        public float $subtotal,
        public string $currency,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public static function fromEntity(Cart $cart): self
    {
        return new self(
            id: $cart->getId()->value(),
            userId: $cart->getUserId()->value(),
            status: $cart->getStatus(),
            convertedToOrderId: $cart->getConvertedToOrderId()?->value(),
            items: CartItemDTO::collection($cart->getItems()),
            itemCount: $cart->itemCount(),
            totalQuantity: $cart->totalQuantity(),
            subtotal: $cart->subtotal()->amount,
            currency: $cart->subtotal()->currency->value,
            createdAt: $cart->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $cart->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
