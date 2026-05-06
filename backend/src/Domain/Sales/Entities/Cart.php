<?php

declare(strict_types=1);

namespace Domain\Sales\Entities;

use Domain\Sales\Exceptions\CartItemNotFoundException;
use Domain\Sales\ValueObjects\CartId;
use Domain\Sales\ValueObjects\CartItemId;
use Domain\Sales\ValueObjects\GuestSessionId;
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
 *
 * ADR-017: Cart artık ya bir kullanıcıya ya da bir guest session'a aittir
 * (XOR). Domain invariant olarak validateOwnership içinde, DB-side
 * chk_carts_owner CHECK constraint'i ile garantilenir.
 */
final class Cart extends AbstractEntity
{
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_CONVERTED = 'converted';
    public const string STATUS_ABANDONED = 'abandoned';

    /** Guest cart cookie TTL — DB cleanup job'u expires_at <= now() taraması yapar. */
    public const int GUEST_CART_TTL_DAYS = 30;

    /** @var array<int, CartItem> */
    private array $items;

    /**
     * @param array<int, CartItem> $items
     */
    public function __construct(
        private readonly CartId $id,
        private readonly ?UserId $userId = null,
        private readonly ?GuestSessionId $guestSessionId = null,
        private string $status = self::STATUS_ACTIVE,
        private ?\DateTimeImmutable $expiresAt = null,
        private ?OrderId $convertedToOrderId = null,
        array $items = [],
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->validateOwnership();
        $this->items = array_values($items);
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * XOR: cart MUTLAKA bir owner'a sahip olmalı, ama hem user hem guest
     * olamaz. DB constraint'i de aynı kuralı uyguluyor.
     */
    private function validateOwnership(): void
    {
        if ($this->userId === null && $this->guestSessionId === null) {
            throw new \InvalidArgumentException('Cart must have either userId or guestSessionId');
        }
        if ($this->userId !== null && $this->guestSessionId !== null) {
            throw new \InvalidArgumentException('Cart cannot have both userId and guestSessionId');
        }
    }

    public static function createForUser(UserId $userId): self
    {
        return new self(
            id: CartId::generate(),
            userId: $userId,
        );
    }

    public static function createForGuest(GuestSessionId $sessionId): self
    {
        return new self(
            id: CartId::generate(),
            guestSessionId: $sessionId,
            expiresAt: new \DateTimeImmutable('+' . self::GUEST_CART_TTL_DAYS . ' days'),
        );
    }

    public function getId(): CartId
    {
        return $this->id;
    }

    public function getUserId(): ?UserId
    {
        return $this->userId;
    }

    public function getGuestSessionId(): ?GuestSessionId
    {
        return $this->guestSessionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConvertedToOrderId(): ?OrderId
    {
        return $this->convertedToOrderId;
    }

    public function isGuest(): bool
    {
        return $this->guestSessionId !== null;
    }

    public function isUser(): bool
    {
        return $this->userId !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null
            && $this->expiresAt < new \DateTimeImmutable();
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
     * "Primary" subtotal — yalnızca ilk item'ın currency'sindeki line total'ları
     * toplar. Mixed currency cart'larda eksik bilgi verir; tam dökümle
     * çalışacak caller subtotalsByCurrency() veya isUniformCurrency()
     * kontrol etsin. Boş sepette Money(0, TRY).
     *
     * Geri-uyumluluk için bu method tutuldu (CartDTO içinden çağrılıyor +
     * tek-currency cart'larda hâlâ doğru sonuç).
     */
    public function subtotal(): Money
    {
        if ($this->items === []) {
            return new Money(0.0, Currency::TRY);
        }
        $primary = $this->items[0]->getUnitPriceSnapshot()->currency;
        $sum = 0.0;
        foreach ($this->items as $item) {
            if ($item->getUnitPriceSnapshot()->currency === $primary) {
                $sum += $item->lineTotal()->amount;
            }
        }
        return new Money(round($sum, 2), $primary);
    }

    /**
     * Para birimi başına subtotal — mixed cart'ı tam dökümlü temsil eder.
     * Frontend her bucket'ı display currency'e ayrı ayrı çevirip toplayabilir.
     *
     * @return array<string, float>  Currency code → subtotal (yuvarlanmış)
     */
    public function subtotalsByCurrency(): array
    {
        $buckets = [];
        foreach ($this->items as $item) {
            $code = $item->getUnitPriceSnapshot()->currency->value;
            $buckets[$code] = ($buckets[$code] ?? 0.0) + $item->lineTotal()->amount;
        }
        foreach ($buckets as $code => $amount) {
            $buckets[$code] = round($amount, 2);
        }
        return $buckets;
    }

    /**
     * Tek currency'li sepet mi? PlaceOrder şu an yalnızca uniform sepetleri
     * kabul ediyor (handler'da kontrol). Frontend de checkout butonunu
     * buna göre disable eder.
     */
    public function isUniformCurrency(): bool
    {
        return count($this->subtotalsByCurrency()) <= 1;
    }

    /**
     * Add a product to the cart. If the product is already present, the
     * existing line's quantity is incremented (uniq_cart_items_cart_product).
     *
     * NOT: Mixed currency artık reddedilmiyor — sepet dump'ı taşıyabilir
     * ama checkout aşamasında PlaceOrderHandler "uniform currency" kuralı
     * uygular. Bu sayede "USD ürünü TRY sepete ekle, sonra TRY ürünleri
     * çıkarıp sade USD checkout" akışı mümkün.
     */
    public function addItem(
        string $productId,
        string $productName,
        string $productSku,
        int $quantity,
        Money $unitPrice,
    ): CartItem {
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
            productNameSnapshot: $productName,
            productSkuSnapshot: $productSku,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'userId' => $this->userId?->value(),
            'guestSessionId' => $this->guestSessionId?->value(),
            'isGuest' => $this->isGuest(),
            'status' => $this->status,
            'expiresAt' => $this->expiresAt?->format(\DateTimeInterface::ATOM),
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
            userId: isset($data['userId']) && $data['userId'] !== null
                ? UserId::fromString((string) $data['userId'])
                : null,
            guestSessionId: isset($data['guestSessionId']) && $data['guestSessionId'] !== null
                ? GuestSessionId::fromString((string) $data['guestSessionId'])
                : null,
            status: (string) ($data['status'] ?? self::STATUS_ACTIVE),
            expiresAt: isset($data['expiresAt']) && $data['expiresAt'] !== null
                ? self::parseTimestamp($data['expiresAt'])
                : null,
            convertedToOrderId: isset($data['convertedToOrderId']) && $data['convertedToOrderId'] !== null
                ? OrderId::fromString((string) $data['convertedToOrderId'])
                : null,
            items: $items ?? [],
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt']) && $data['updatedAt'] !== null
                ? self::parseTimestamp($data['updatedAt'])
                : null,
        );
    }
}
