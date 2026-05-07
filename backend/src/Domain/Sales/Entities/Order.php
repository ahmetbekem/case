<?php

declare(strict_types=1);

namespace Domain\Sales\Entities;

use Domain\Sales\Enums\OrderStatus;
use Domain\Sales\Exceptions\OrderAccessDeniedException;
use Domain\Sales\Exceptions\OrderCannotBeCancelledException;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;

/**
 * Order aggregate root. Once created the order's items, totals, and
 * exchange-rate snapshot are immutable; only the status and its
 * timestamps move through the state machine: pending → completed |
 * cancelled (both terminal).
 *
 * IDOR protection: assertCanBeViewedBy() / assertCanBeCancelledBy()
 * raise OrderAccessDeniedException (which renders as 404, not 403, to
 * avoid leaking the existence of someone else's order — SECURITY.md §5.3).
 */
final class Order extends AbstractEntity
{
    /** @var array<int, OrderItem> */
    private array $items;

    /**
     * ADR-018 — deliveryAddressSnapshot ve billingAddressSnapshot
     * yeni siparişler için ZORUNLU (PlaceOrderHandler her zaman
     * doldurur). Persistence'tan hidrate edilen ESKİ siparişler
     * (migration öncesi) null snapshot'lı olabilir; bu yüzden
     * default null + hidrate uyumluluğu.
     *
     * deliveryAddressId / billingAddressId nullable — adres soft
     * delete edilince DB FK ON DELETE SET NULL ile null'a düşer.
     * Snapshot her hâlükârda korur.
     *
     * @param array<int, OrderItem>     $items
     * @param array<string, mixed>|null $deliveryAddressSnapshot
     * @param array<string, mixed>|null $billingAddressSnapshot
     */
    public function __construct(
        private readonly OrderId $id,
        private readonly string $orderNumber,
        private readonly UserId $userId,
        private OrderStatus $status,
        private readonly Money $subtotal,
        private readonly Money $total,
        private readonly Currency $displayCurrency,
        private readonly Money $displayTotal,
        private readonly float $exchangeRateSnapshot,
        private ?string $notes = null,
        array $items = [],
        private ?\DateTimeImmutable $cancelledAt = null,
        private ?string $cancelledReason = null,
        private ?\DateTimeImmutable $completedAt = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?\DateTimeImmutable $updatedAt = null,
        private readonly ?array $deliveryAddressSnapshot = null,
        private readonly ?array $billingAddressSnapshot = null,
        private readonly ?string $deliveryAddressId = null,
        private readonly ?string $billingAddressId = null,
    ) {
        if ($items === []) {
            throw new \InvalidArgumentException('Order must contain at least one item');
        }
        if ($exchangeRateSnapshot <= 0) {
            throw new \InvalidArgumentException('Exchange rate snapshot must be positive');
        }
        $this->items = array_values($items);
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDeliveryAddressSnapshot(): ?array
    {
        return $this->deliveryAddressSnapshot;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBillingAddressSnapshot(): ?array
    {
        return $this->billingAddressSnapshot;
    }

    public function getDeliveryAddressId(): ?string
    {
        return $this->deliveryAddressId;
    }

    public function getBillingAddressId(): ?string
    {
        return $this->billingAddressId;
    }

    public function getId(): OrderId
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getDisplayCurrency(): Currency
    {
        return $this->displayCurrency;
    }

    public function getDisplayTotal(): Money
    {
        return $this->displayTotal;
    }

    public function getExchangeRateSnapshot(): float
    {
        return $this->exchangeRateSnapshot;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancelledReason(): ?string
    {
        return $this->cancelledReason;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /**
     * @return array<int, OrderItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function itemCount(): int
    {
        return count($this->items);
    }

    public function cancel(string $reason): void
    {
        if (!$this->status->isPending()) {
            throw OrderCannotBeCancelledException::wrongStatus($this->id->value(), $this->status);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Cancel reason is required');
        }
        $this->status = OrderStatus::CANCELLED;
        $this->cancelledAt = new \DateTimeImmutable();
        $this->cancelledReason = $reason;
        $this->markAsUpdated();
    }

    public function complete(): void
    {
        if (!$this->status->isPending()) {
            throw new \LogicException("Only pending orders can be completed (current: {$this->status->value})");
        }
        $this->status = OrderStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        $this->markAsUpdated();
    }

    /**
     * IDOR guard for read endpoints. Admin sees any order; customer only
     * sees their own. Any other case → OrderAccessDeniedException
     * (rendered as 404 to prevent enumeration).
     */
    public function assertCanBeViewedBy(UserId $viewerId, ?string $viewerRole): void
    {
        if ($viewerRole === 'admin') {
            return;
        }
        if (!$this->userId->equals($viewerId)) {
            throw OrderAccessDeniedException::notOwner();
        }
    }

    /**
     * Cancellation authorisation: same ownership rule as view (admin OR owner).
     */
    public function assertCanBeCancelledBy(UserId $viewerId, ?string $viewerRole): void
    {
        $this->assertCanBeViewedBy($viewerId, $viewerRole);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'orderNumber' => $this->orderNumber,
            'userId' => $this->userId->value(),
            'status' => $this->status->value,
            'subtotal' => $this->subtotal->amount,
            'total' => $this->total->amount,
            'currency' => $this->subtotal->currency->value,
            'displayCurrency' => $this->displayCurrency->value,
            'displayTotal' => $this->displayTotal->amount,
            'exchangeRateSnapshot' => $this->exchangeRateSnapshot,
            'notes' => $this->notes,
            'items' => array_map(fn (OrderItem $i) => $i->toArray(), $this->items),
            'itemCount' => $this->itemCount(),
            'cancelledAt' => $this->cancelledAt?->format(\DateTimeInterface::ATOM),
            'cancelledReason' => $this->cancelledReason,
            'completedAt' => $this->completedAt?->format(\DateTimeInterface::ATOM),
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
            'deliveryAddress' => $this->deliveryAddressSnapshot,
            'billingAddress' => $this->billingAddressSnapshot,
            'deliveryAddressId' => $this->deliveryAddressId,
            'billingAddressId' => $this->billingAddressId,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, OrderItem> $items
     */
    public static function fromArray(array $data, array $items = []): self
    {
        $baseCurrency = Currency::from((string) $data['currency']);
        $displayCurrency = Currency::from((string) $data['displayCurrency']);

        return new self(
            id: OrderId::fromString((string) $data['id']),
            orderNumber: (string) $data['orderNumber'],
            userId: UserId::fromString((string) $data['userId']),
            status: OrderStatus::from((string) $data['status']),
            subtotal: new Money((float) $data['subtotal'], $baseCurrency),
            total: new Money((float) $data['total'], $baseCurrency),
            displayCurrency: $displayCurrency,
            displayTotal: new Money((float) $data['displayTotal'], $displayCurrency),
            exchangeRateSnapshot: (float) $data['exchangeRateSnapshot'],
            notes: $data['notes'] ?? null,
            items: $items,
            cancelledAt: isset($data['cancelledAt']) ? self::parseTimestamp($data['cancelledAt']) : null,
            cancelledReason: $data['cancelledReason'] ?? null,
            completedAt: isset($data['completedAt']) ? self::parseTimestamp($data['completedAt']) : null,
            createdAt: self::parseTimestamp($data['createdAt']),
            updatedAt: isset($data['updatedAt']) ? self::parseTimestamp($data['updatedAt']) : null,
            deliveryAddressSnapshot: isset($data['deliveryAddress']) && is_array($data['deliveryAddress'])
                ? $data['deliveryAddress']
                : null,
            billingAddressSnapshot: isset($data['billingAddress']) && is_array($data['billingAddress'])
                ? $data['billingAddress']
                : null,
            deliveryAddressId: isset($data['deliveryAddressId']) && $data['deliveryAddressId'] !== null
                ? (string) $data['deliveryAddressId']
                : null,
            billingAddressId: isset($data['billingAddressId']) && $data['billingAddressId'] !== null
                ? (string) $data['billingAddressId']
                : null,
        );
    }
}
