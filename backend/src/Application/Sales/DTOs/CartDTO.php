<?php

declare(strict_types=1);

namespace Application\Sales\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Sales\Entities\Cart;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CartDTO',
    title: 'Cart (aggregate)',
    description: 'Kullanıcının aktif sepeti — Cart aggregate root\'unun dış dünya temsili. '
        . 'Mixed currency destekli: subtotalsByCurrency her para birimi için ayrı bucket; '
        . 'subtotal + currency yalnızca uniform sepetlerde anlamlıdır (ilk item\'ın currency\'si).',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'userId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'converted'], description: 'Sipariş\'e dönüşmüş sepet "converted" olur'),
        new OA\Property(property: 'convertedToOrderId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CartItemDTO')),
        new OA\Property(property: 'itemCount', type: 'integer', description: 'Distinct kalem sayısı'),
        new OA\Property(property: 'totalQuantity', type: 'integer', description: 'Tüm kalemlerin quantity toplamı'),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', description: 'Primary (ilk item) currency\'sindeki line total\'lar toplamı'),
        new OA\Property(property: 'currency', type: 'string', enum: ['TRY', 'USD', 'EUR']),
        new OA\Property(
            property: 'subtotalsByCurrency',
            type: 'object',
            description: 'Her para birimi için ayrı subtotal — mixed cart\'larda UI tarafından tek display currency\'e dönüştürülür.',
            additionalProperties: new OA\AdditionalProperties(type: 'number', format: 'float'),
            example: ['TRY' => 758.00, 'USD' => 89.90],
        ),
        new OA\Property(property: 'isUniformCurrency', type: 'boolean', description: 'true ise checkout aktif edilebilir; false ise PlaceOrder reddeder'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final readonly class CartDTO extends AbstractOutputDTO
{
    /**
     * @param array<int, CartItemDTO> $items
     * @param array<string, float> $subtotalsByCurrency
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
        public array $subtotalsByCurrency,
        public bool $isUniformCurrency,
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
            subtotalsByCurrency: $cart->subtotalsByCurrency(),
            isUniformCurrency: $cart->isUniformCurrency(),
            createdAt: $cart->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $cart->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
