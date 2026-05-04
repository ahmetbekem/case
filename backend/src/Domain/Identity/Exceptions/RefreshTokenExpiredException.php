<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

final class RefreshTokenExpiredException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::UNAUTHORIZED;
    }

    public static function withId(string $id): self
    {
        return new self(
            message: 'Refresh token has expired',
            errorCode: 'REFRESH_TOKEN_EXPIRED',
            context: ['tokenId' => $id],
        );
    }
}
