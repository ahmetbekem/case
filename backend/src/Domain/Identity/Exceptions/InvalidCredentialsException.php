<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

final class InvalidCredentialsException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::UNAUTHORIZED;
    }

    /**
     * Generic factory — never reveal which half (email vs password) failed.
     * Doing so leaks whether an account exists (user enumeration).
     */
    public static function forLogin(): self
    {
        return new self(
            message: 'Invalid email or password',
            errorCode: 'INVALID_CREDENTIALS',
        );
    }

    public static function forInactiveAccount(): self
    {
        return new self(
            message: 'Account is inactive',
            errorCode: 'ACCOUNT_INACTIVE',
        );
    }
}
