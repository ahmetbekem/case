<?php

declare(strict_types=1);

namespace Domain\Currency\Exceptions;

use Domain\Shared\Exceptions\ValidationException;

final class UnsupportedCurrencyException extends ValidationException
{
    public static function withCode(string $code): self
    {
        return new self(
            message: 'Currency is not supported',
            errorCode: 'UNSUPPORTED_CURRENCY',
            context: ['code' => $code, 'supported' => ['TRY', 'USD', 'EUR']],
        );
    }
}
