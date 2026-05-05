<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Mappers;

use Domain\Sales\Entities\CartItem;
use Domain\Shared\Abstracts\AbstractMapper;
use Infrastructure\Sales\Models\CartItemModel;

final class CartItemMapper extends AbstractMapper
{
    public function toEntity(object $model): CartItem
    {
        assert($model instanceof CartItemModel);

        return CartItem::fromArray([
            'id' => $model->id,
            'cartId' => $model->cart_id,
            'productId' => $model->product_id,
            'quantity' => (int) $model->quantity,
            'unitPriceSnapshot' => (float) $model->unit_price_snapshot,
            'currencySnapshot' => $model->currency_snapshot,
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
            'updatedAt' => $model->updated_at?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(object $entity): array
    {
        assert($entity instanceof CartItem);

        return [
            'id' => $entity->getId()->value(),
            'cart_id' => $entity->getCartId()->value(),
            'product_id' => $entity->getProductId(),
            'quantity' => $entity->getQuantity(),
            'unit_price_snapshot' => $entity->getUnitPriceSnapshot()->amount,
            'currency_snapshot' => $entity->getUnitPriceSnapshot()->currency->value,
            'created_at' => $entity->getCreatedAt(),
            'updated_at' => $entity->getUpdatedAt(),
        ];
    }
}
