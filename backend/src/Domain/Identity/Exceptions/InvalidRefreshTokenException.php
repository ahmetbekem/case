<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

final class InvalidRefreshTokenException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::UNAUTHORIZED;
    }

    public static function notFound(): self
    {
        return new self(
            message: 'Refresh token is invalid',
            errorCode: 'INVALID_REFRESH_TOKEN',
        );
    }

    public static function missing(): self
    {
        return new self(
            message: 'Refresh token is missing',
            errorCode: 'MISSING_REFRESH_TOKEN',
        );
    }
}
