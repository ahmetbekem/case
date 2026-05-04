<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

/**
 * Raised when an already-rotated refresh token is presented again past the
 * grace window — strong evidence the original token was stolen. The handler
 * MUST revoke the entire family and emit a critical-severity audit event.
 *
 * @see SECURITY.md §4.4 — Refresh Token Rotation & Replay Detection
 */
final class RefreshTokenReplayException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::UNAUTHORIZED;
    }

    public static function detected(string $familyId, string $userId): self
    {
        return new self(
            message: 'Refresh token replay detected — family revoked',
            errorCode: 'REFRESH_TOKEN_REPLAY',
            context: ['familyId' => $familyId, 'userId' => $userId],
        );
    }
}
