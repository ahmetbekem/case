<?php

declare(strict_types=1);

namespace Application\Sales\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Sales\Entities\Cart;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CartDTO',
    title: 'Cart (aggregate)',
    description: 'Kullanıcının aktif sepeti — Cart aggregate root\'unun dış dünya temsili.',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'userId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'converted'], description: 'Sipariş\'e dönüşmüş sepet "converted" olur'),
        new OA\Property(property: 'convertedToOrderId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CartItemDTO')),
        new OA\Property(property: 'itemCount', type: 'integer', description: 'Distinct kalem sayısı'),
        new OA\Property(property: 'totalQuantity', type: 'integer', description: 'Tüm kalemlerin quantity toplamı'),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float'),
        new OA\Property(property: 'currency', type: 'string', enum: ['TRY', 'USD', 'EUR']),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
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
