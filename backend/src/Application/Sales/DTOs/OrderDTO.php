<?php

declare(strict_types=1);

namespace Application\Sales\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Sales\Entities\Order;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderDTO',
    title: 'Order (aggregate)',
    description: 'Sipariş aggregate — base currency\'de subtotal/total tutar + '
        . 'sipariş anında dondurulmuş display currency dönüşümü (snapshot).',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'orderNumber', type: 'string', example: 'ORD-2026-000123', description: 'İnsan okunur sipariş numarası'),
        new OA\Property(property: 'userId', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'completed', 'cancelled']),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float'),
        new OA\Property(property: 'total', type: 'number', format: 'float'),
        new OA\Property(property: 'currency', type: 'string', enum: ['TRY', 'USD', 'EUR'], description: 'Sipariş base currency'),
        new OA\Property(property: 'displayCurrency', type: 'string', enum: ['TRY', 'USD', 'EUR']),
        new OA\Property(property: 'displayTotal', type: 'number', format: 'float'),
        new OA\Property(property: 'exchangeRateSnapshot', type: 'number', format: 'float', example: 32.5430),
        new OA\Property(property: 'notes', type: 'string', nullable: true, maxLength: 5000),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItemDTO')),
        new OA\Property(property: 'itemCount', type: 'integer'),
        new OA\Property(property: 'cancelledAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'cancelledReason', type: 'string', nullable: true),
        new OA\Property(property: 'completedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'deliveryAddress', type: 'object', nullable: true, description: 'ADR-018 — sipariş anında dondurulmuş JSONB snapshot. Adres sonradan silinse bile burada kalır.'),
        new OA\Property(property: 'billingAddress', type: 'object', nullable: true, description: 'Teslimat ile aynı olabilir; sameBillingAddress audit metadata\'sında işaretli.'),
        new OA\Property(property: 'deliveryAddressId', type: 'string', format: 'uuid', nullable: true, description: 'Orijinal adres referansı; soft-delete sonrası null\'a düşer.'),
        new OA\Property(property: 'billingAddressId', type: 'string', format: 'uuid', nullable: true),
    ],
    type: 'object',
)]
final readonly class OrderDTO extends AbstractOutputDTO
{
    /**
     * @param array<int, OrderItemDTO> $items
     */
    /**
     * @param array<int, OrderItemDTO> $items
     * @param array<string, mixed>|null $deliveryAddress
     * @param array<string, mixed>|null $billingAddress
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
        public ?array $deliveryAddress,
        public ?array $billingAddress,
        public ?string $deliveryAddressId,
        public ?string $billingAddressId,
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
            deliveryAddress: $order->getDeliveryAddressSnapshot(),
            billingAddress: $order->getBillingAddressSnapshot(),
            deliveryAddressId: $order->getDeliveryAddressId(),
            billingAddressId: $order->getBillingAddressId(),
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
