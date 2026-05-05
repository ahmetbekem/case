<?php

declare(strict_types=1);

namespace Infrastructure\Sales\Mappers;

use Domain\Sales\Entities\OrderItem;
use Domain\Shared\Abstracts\AbstractMapper;
use Infrastructure\Sales\Models\OrderItemModel;

final class OrderItemMapper extends AbstractMapper
{
    public function toEntity(object $model): OrderItem
    {
        assert($model instanceof OrderItemModel);

        // Snapshot rows store unit_price already in TRY (we don't carry
        // a currency column on order_items — they always match the
        // order's base currency, which is TRY today).
        return OrderItem::fromArray([
            'id' => $model->id,
            'orderId' => $model->order_id,
            'productId' => $model->product_id,
            'productName' => $model->product_name_snapshot,
            'productSku' => $model->product_sku_snapshot,
            'quantity' => (int) $model->quantity,
            'unitPrice' => (float) $model->unit_price,
            'currency' => 'TRY',
            'lineTotal' => (float) $model->line_total,
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(object $entity): array
    {
        assert($entity instanceof OrderItem);

        return [
            'id' => $entity->getId()->value(),
            'order_id' => $entity->getOrderId()->value(),
            'product_id' => $entity->getProductId(),
            'product_name_snapshot' => $entity->getProductNameSnapshot(),
            'product_sku_snapshot' => $entity->getProductSkuSnapshot(),
            'quantity' => $entity->getQuantity(),
            'unit_price' => $entity->getUnitPrice()->amount,
            'line_total' => $entity->getLineTotal()->amount,
            'created_at' => $entity->getCreatedAt(),
        ];
    }
}
