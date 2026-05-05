<?php

declare(strict_types=1);

namespace Application\Catalog\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * Stock adjustment: positive delta = restock, negative = correction.
 * Negative deltas that would push the stock below zero are rejected by
 * Product::decreaseStock and surface as 409 INSUFFICIENT_STOCK.
 */
final readonly class AdjustStockCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $productId,
        public int $delta,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'AdjustStock';
    }
}
