<?php

declare(strict_types=1);

namespace Domain\Sales\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

final class OrderNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(
            message: 'Order not found',
            errorCode: 'ORDER_NOT_FOUND',
            context: ['orderId' => $id],
        );
    }
}
