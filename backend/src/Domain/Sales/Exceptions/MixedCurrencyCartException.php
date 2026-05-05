<?php

declare(strict_types=1);

namespace Domain\Sales\Exceptions;

use Domain\Shared\Exceptions\BusinessRuleException;

/**
 * Mixed-currency cart artık ekleme aşamasında reddedilmiyor (Cart aggregate
 * permissive). Ama PlaceOrder tek currency'lik base subtotal hesabı yaptığı
 * için checkout aşamasında reject ediyor — bu exception 409 ile UI'a
 * "tek currency'ye düşürün" yönlendirmesi yapar.
 *
 * @param array<int, string> $currencies
 */
final class MixedCurrencyCartException extends BusinessRuleException
{
    /**
     * @param array<int, string> $currencies
     */
    public static function cannotPlaceOrder(array $currencies): self
    {
        return new self(
            message: 'Cart contains multiple currencies (' . implode(', ', $currencies)
                . '). Checkout requires a single currency — please remove items so that all remaining items share one currency.',
            errorCode: 'CART_MIXED_CURRENCY',
            context: ['currencies' => $currencies],
        );
    }
}
