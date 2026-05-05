<?php

declare(strict_types=1);

namespace Domain\Sales\Contracts;

use Domain\Sales\Entities\Order;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Shared\Contracts\RepositoryInterface;
use Domain\Shared\ValueObjects\UserId;

/**
 * @extends RepositoryInterface<Order, OrderId>
 */
interface OrderRepositoryInterface extends RepositoryInterface
{
    public function findById(OrderId $id): ?Order;

    /**
     * @return array<int, Order>
     */
    public function findByUser(UserId $userId, int $page = 1, int $pageSize = 20): array;

    public function countForUser(UserId $userId): int;

    /**
     * @return array<int, Order>
     */
    public function paginateAll(int $page = 1, int $pageSize = 20): array;

    public function countAll(): int;

    /**
     * Persist the order aggregate (Order row + every OrderItem row).
     * Order items are immutable so no removal-sync is needed.
     */
    public function save(Order $order): void;

    /**
     * Generates the next human-readable order number from the
     * sales.order_number_seq PostgreSQL sequence. Format:
     * ORD-{YYYY}-{seq:06d}.
     */
    public function generateNumber(): string;
}
