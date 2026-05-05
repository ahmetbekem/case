<?php

declare(strict_types=1);

namespace Application\Sales\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Sales\Entities\Order;

final readonly class OrderDTO extends AbstractOutputDTO
{
    /**
     * @param array<int, OrderItemDTO> $items
     */
    public function __construct(
        public string $id,
        public string $orderNumber,
        public string $userId,
        public string $status,
        public float $subtotal,
        public float $total,
        public string $currency,
        public string $displayCurrency,
        public float $displayTotal,
        public float $exchangeRateSnapshot,
        public ?string $notes,
        public array $items,
        public int $itemCount,
        public ?string $cancelledAt,
        public ?string $cancelledReason,
        public ?string $completedAt,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getId()->value(),
            orderNumber: $order->getOrderNumber(),
            userId: $order->getUserId()->value(),
            status: $order->getStatus()->value,
            subtotal: $order->getSubtotal()->amount,
            total: $order->getTotal()->amount,
            currency: $order->getSubtotal()->currency->value,
            displayCurrency: $order->getDisplayCurrency()->value,
            displayTotal: $order->getDisplayTotal()->amount,
            exchangeRateSnapshot: $order->getExchangeRateSnapshot(),
            notes: $order->getNotes(),
            items: OrderItemDTO::collection($order->getItems()),
            itemCount: $order->itemCount(),
            cancelledAt: $order->getCancelledAt()?->format(\DateTimeInterface::ATOM),
            cancelledReason: $order->getCancelledReason(),
            completedAt: $order->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            createdAt: $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $order->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<int, Order> $orders
     * @return array<int, self>
     */
    public static function collection(array $orders): array
    {
        return array_map(fn (Order $o) => self::fromEntity($o), $orders);
    }
}
