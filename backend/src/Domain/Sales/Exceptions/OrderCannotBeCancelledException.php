<?php

declare(strict_types=1);

namespace Domain\Sales\Exceptions;

use Domain\Sales\Enums\OrderStatus;
use Domain\Shared\Exceptions\BusinessRuleException;

final class OrderCannotBeCancelledException extends BusinessRuleException
{
    public static function wrongStatus(string $orderId, OrderStatus $status): self
    {
        return new self(
            message: 'Order cannot be cancelled in its current status',
            errorCode: 'ORDER_CANNOT_BE_CANCELLED',
            context: [
                'orderId' => $orderId,
                'currentStatus' => $status->value,
                'allowedFromStatus' => OrderStatus::PENDING->value,
            ],
        );
    }
}
