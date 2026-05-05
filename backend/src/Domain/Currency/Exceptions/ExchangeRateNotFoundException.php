<?php

declare(strict_types=1);

namespace Domain\Currency\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

final class ExchangeRateNotFoundException extends NotFoundException
{
    public static function forPair(string $base, string $target): self
    {
        return new self(
            message: 'No exchange rate is available for this currency pair',
            errorCode: 'EXCHANGE_RATE_NOT_FOUND',
            context: ['base' => $base, 'target' => $target],
        );
    }
}
