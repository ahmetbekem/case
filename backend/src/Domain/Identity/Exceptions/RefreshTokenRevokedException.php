<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

final class RefreshTokenRevokedException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::UNAUTHORIZED;
    }

    public static function withReason(string $id, string $reason): self
    {
        return new self(
            message: 'Refresh token has been revoked',
            errorCode: 'REFRESH_TOKEN_REVOKED',
            context: ['tokenId' => $id, 'reason' => $reason],
        );
    }
}
