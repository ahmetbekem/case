<?php

declare(strict_types=1);

namespace Domain\Sales\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

final class CartItemNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(
            message: 'Cart item not found',
            errorCode: 'CART_ITEM_NOT_FOUND',
            context: ['cartItemId' => $id],
        );
    }
}
