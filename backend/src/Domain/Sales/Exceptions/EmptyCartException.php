<?php

declare(strict_types=1);

namespace Domain\Sales\Exceptions;

use Domain\Shared\Exceptions\BusinessRuleException;

final class EmptyCartException extends BusinessRuleException
{
    public static function cannotPlaceOrder(): self
    {
        return new self(
            message: 'Cannot place an order for an empty cart',
            errorCode: 'EMPTY_CART',
        );
    }
}
