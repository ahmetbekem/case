<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

/**
 * Thrown by JwtAuthMiddleware when the request carries no token, an
 * expired one, or one whose signature does not verify. Distinct from
 * InvalidCredentialsException (which is raised inside login flow); this
 * one signals "you are not currently authenticated", not "your password
 * is wrong".
 */
final class UnauthenticatedException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::UNAUTHORIZED;
    }

    public static function missingToken(): self
    {
        return new self(
            message: 'Access token is missing',
            errorCode: 'MISSING_ACCESS_TOKEN',
        );
    }

    public static function invalidToken(): self
    {
        return new self(
            message: 'Access token is invalid or expired',
            errorCode: 'INVALID_ACCESS_TOKEN',
        );
    }
}
