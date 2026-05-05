<?php

declare(strict_types=1);

namespace Domain\Sales\Entities;

use Domain\Sales\ValueObjects\OrderId;
use Domain\Sales\ValueObjects\OrderItemId;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;

/**
 * Immutable line item — once persisted with the order it never changes
 * (DATABASE_SCHEMA §5.4: no updated_at column). Snapshot fields hold
 * the product's name/SKU at sale time so a later product rename never
 * rewrites order history.
 */
final class OrderItem extends AbstractEntity
{
    public function __construct(
        private readonly OrderItemId $id,
        private readonly OrderId $orderId,
        private readonly string $productId,
        private readonly string $productNameSnapshot,
        private readonly string $productSkuSnapshot,
        private readonly int $quantity,
        private readonly Money $unitPrice,
        private readonly Money $lineTotal,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Order item quantity must be positive');
        }
        $this->createdAt = $createdAt;
    }

    public function getId(): OrderItemId
    {
        return $this->id;
    }

    public function getOrderId(): OrderId
    {
        return $this->orderId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getProductNameSnapshot(): string
    {
        return $this->productNameSnapshot;
    }

    public function getProductSkuSnapshot(): string
    {
        return $this->productSkuSnapshot;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function getLineTotal(): Money
    {
        return $this->lineTotal;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'orderId' => $this->orderId->value(),
            'productId' => $this->productId,
            'productName' => $this->productNameSnapshot,
            'productSku' => $this->productSkuSnapshot,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice->amount,
            'currency' => $this->unitPrice->currency->value,
            'lineTotal' => $this->lineTotal->amount,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $currency = Currency::from((string) $data['currency']);
        return new self(
            id: OrderItemId::fromString((string) $data['id']),
            orderId: OrderId::fromString((string) $data['orderId']),
            productId: (string) $data['productId'],
            productNameSnapshot: (string) $data['productName'],
            productSkuSnapshot: (string) $data['productSku'],
            quantity: (int) $data['quantity'],
            unitPrice: new Money((float) $data['unitPrice'], $currency),
            lineTotal: new Money((float) $data['lineTotal'], $currency),
            createdAt: self::parseTimestamp($data['createdAt']),
        );
    }
}
