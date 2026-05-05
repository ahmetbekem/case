<?php

declare(strict_types=1);

namespace Domain\Sales\Entities;

use Domain\Sales\Exceptions\CartItemNotFoundException;
use Domain\Sales\ValueObjects\CartId;
use Domain\Sales\ValueObjects\CartItemId;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;

/**
 * Cart aggregate root. Owns its CartItem collection — addItem /
 * updateItemQuantity / removeItem / clear all mutate items through the
 * aggregate so domain invariants stay enforceable from one place
 * (one-product-per-cart, positive quantity, status transitions).
 */
final class Cart extends AbstractEntity
{
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_CONVERTED = 'converted';
    public const string STATUS_ABANDONED = 'abandoned';

    /** @var array<int, CartItem> */
    private array $items;

    /**
     * @param array<int, CartItem> $items
     */
    public function __construct(
        private readonly CartId $id,
        private readonly UserId $userId,
        private string $status = self::STATUS_ACTIVE,
        private ?OrderId $convertedToOrderId = null,
        array $items = [],
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->items = array_values($items);
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    // ─── Getters ────────────────────────────────────────────────

    public function getId(): CartId
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getConvertedToOrderId(): ?OrderId
    {
        return $this->convertedToOrderId;
    }

    /**
     * @return array<int, CartItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function itemCount(): int
    {
        return count($this->items);
    }

    public function totalQuantity(): int
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $sum += $item->getQuantity();
        }
        return $sum;
    }

    /**
     * Sum of all line totals — assumes every item shares the same
     * currency (enforced by addItem). Returns Money(0, TRY) for empty.
     */
    public function subtotal(): Money
    {
        if ($this->items === []) {
            return new Money(0.0, Currency::TRY);
        }
        $currency = $this->items[0]->getUnitPriceSnapshot()->currency;
        $sum = 0.0;
        foreach ($this->items as $item) {
            if ($item->getUnitPriceSnapshot()->currency !== $currency) {
                throw new \LogicException('Cart contains mixed currencies — invariant broken');
            }
            $sum += $item->lineTotal()->amount;
        }
        return new Money(round($sum, 2), $currency);
    }

    // ─── Behaviour ──────────────────────────────────────────────

    /**
     * Add a product to the cart. If the product is already present, the
     * existing line's quantity is incremented (uniq_cart_items_cart_product).
     */
    public function addItem(string $productId, int $quantity, Money $unitPrice): CartItem
    {
        $this->assertActive();

        $existing = $this->findItemByProduct($productId);
        if ($existing !== null) {
            $existing->incrementQuantity($quantity);
            $this->markAsUpdated();
            return $existing;
        }

        $item = new CartItem(
            id: CartItemId::generate(),
            cartId: $this->id,
            productId: $productId,
            quantity: $quantity,
            unitPriceSnapshot: $unitPrice,
        );
        $this->items[] = $item;
        $this->markAsUpdated();
        return $item;
    }

    public function updateItemQuantity(CartItemId $itemId, int $newQuantity): void
    {
        $this->assertActive();
        $item = $this->findItemById($itemId);
        if ($item === null) {
            throw CartItemNotFoundException::withId($itemId->value());
        }
        $item->changeQuantity($newQuantity);
        $this->markAsUpdated();
    }

    public function removeItem(CartItemId $itemId): void
    {
        $this->assertActive();
        foreach ($this->items as $i => $item) {
            if ($item->getId()->value() === $itemId->value()) {
                unset($this->items[$i]);
                $this->items = array_values($this->items);
                $this->markAsUpdated();
                return;
            }
        }
        throw CartItemNotFoundException::withId($itemId->value());
    }

    public function clear(): void
    {
        $this->assertActive();
        if ($this->items !== []) {
            $this->items = [];
            $this->markAsUpdated();
        }
    }

    public function markAsConverted(OrderId $orderId): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new \LogicException(
                "Cart status must be 'active' to convert (currently '{$this->status}')",
            );
        }
        $this->status = self::STATUS_CONVERTED;
        $this->convertedToOrderId = $orderId;
        $this->markAsUpdated();
    }

    public function markAsAbandoned(): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return;
        }
        $this->status = self::STATUS_ABANDONED;
        $this->markAsUpdated();
    }

    // ─── Internal ───────────────────────────────────────────────

    private function findItemByProduct(string $productId): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getProductId() === $productId) {
                return $item;
            }
        }
        return null;
    }

    private function findItemById(CartItemId $itemId): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getId()->value() === $itemId->value()) {
                return $item;
            }
        }
        return null;
    }

    private function assertActive(): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new \LogicException("Cart is not active (status: {$this->status})");
        }
    }

    // ─── Serialisation ──────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'userId' => $this->userId->value(),
            'status' => $this->status,
            'convertedToOrderId' => $this->convertedToOrderId?->value(),
            'items' => array_map(fn (CartItem $i) => $i->toArray(), $this->items),
            'itemCount' => $this->itemCount(),
            'totalQuantity' => $this->totalQuantity(),
            'subtotal' => $this->subtotal()->amount,
            'currency' => $this->subtotal()->currency->value,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, CartItem>|null $items hydrated CartItem objects
     */
    public static function fromArray(array $data, ?array $items = null): self
    {
        return new self(
            id: CartId::fromString((string) $data['id']),
            userId: UserId::fromString((string) $data['userId']),
            status: (string) ($data['status'] ?? self::STATUS_ACTIVE),
            convertedToOrderId: isset($data['convertedToOrderId'])
                ? OrderId::fromString((string) $data['convertedToOrderId'])
                : null,
            items: $items ?? [],
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt']) ? self::parseTimestamp($data['updatedAt']) : null,
        );
    }
}
