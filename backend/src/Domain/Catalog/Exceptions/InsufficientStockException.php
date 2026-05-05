<?php

declare(strict_types=1);

namespace Domain\Catalog\Exceptions;

use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\Exceptions\BusinessRuleException;

final class InsufficientStockException extends BusinessRuleException
{
    public static function forProduct(ProductId $productId, int $available, int $requested): self
    {
        return new self(
            message: 'Insufficient stock',
            errorCode: 'INSUFFICIENT_STOCK',
            context: [
                'productId' => $productId->value(),
                'available' => $available,
                'requested' => $requested,
            ],
        );
    }
}
