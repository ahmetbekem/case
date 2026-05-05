<?php

declare(strict_types=1);

namespace Domain\Sales\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

/**
 * Surfaces as 404 (not 403) on purpose: a 403 would leak the existence of
 * someone else's order to an attacker probing IDs. From the unauthenticated/
 * non-owner caller's perspective the resource simply "doesn't exist".
 *
 * @see SECURITY.md §5.3 — Resource Ownership / IDOR Prevention
 */
final class OrderAccessDeniedException extends NotFoundException
{
    public static function notOwner(): self
    {
        return new self(
            message: 'Order not found',
            errorCode: 'ORDER_NOT_FOUND',
        );
    }
}
