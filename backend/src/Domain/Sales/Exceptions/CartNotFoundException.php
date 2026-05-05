<?php

declare(strict_types=1);

namespace Domain\Sales\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

final class CartNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(
            message: 'Cart not found',
            errorCode: 'CART_NOT_FOUND',
            context: ['cartId' => $id],
        );
    }

    public static function forUser(string $userId): self
    {
        return new self(
            message: 'No active cart for user',
            errorCode: 'CART_NOT_FOUND',
            context: ['userId' => $userId],
        );
    }
}
