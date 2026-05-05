<?php

declare(strict_types=1);

namespace Domain\Sales\Entities;

use Domain\Sales\ValueObjects\CartId;
use Domain\Sales\ValueObjects\CartItemId;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;

final class CartItem extends AbstractEntity
{
    public function __construct(
        private readonly CartItemId $id,
        private readonly CartId $cartId,
        private readonly string $productId,
        private int $quantity,
        private readonly Money $unitPriceSnapshot,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Cart item quantity must be positive');
        }
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): CartItemId
    {
        return $this->id;
    }

    public function getCartId(): CartId
    {
        return $this->cartId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPriceSnapshot(): Money
    {
        return $this->unitPriceSnapshot;
    }

    public function lineTotal(): Money
    {
        return $this->unitPriceSnapshot->multiply($this->quantity);
    }

    public function changeQuantity(int $newQuantity): void
    {
        if ($newQuantity <= 0) {
            throw new \InvalidArgumentException('Cart item quantity must be positive');
        }
        $this->quantity = $newQuantity;
        $this->markAsUpdated();
    }

    public function incrementQuantity(int $by): void
    {
        if ($by <= 0) {
            throw new \InvalidArgumentException('Increment must be positive');
        }
        $this->quantity += $by;
        $this->markAsUpdated();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'cartId' => $this->cartId->value(),
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'unitPriceSnapshot' => $this->unitPriceSnapshot->amount,
            'currencySnapshot' => $this->unitPriceSnapshot->currency->value,
            'lineTotal' => $this->lineTotal()->amount,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: CartItemId::fromString((string) $data['id']),
            cartId: CartId::fromString((string) $data['cartId']),
            productId: (string) $data['productId'],
            quantity: (int) $data['quantity'],
            unitPriceSnapshot: new Money(
                (float) $data['unitPriceSnapshot'],
                Currency::from((string) $data['currencySnapshot']),
            ),
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt']) ? self::parseTimestamp($data['updatedAt']) : null,
        );
    }
}
