<?php

declare(strict_types=1);

namespace Domain\Security\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

/**
 * State-changing requests (POST/PUT/PATCH/DELETE) must carry an Origin
 * (or Referer) header matching one of the allowed origins. Violation
 * implies a cross-site request — block + audit.
 *
 * @see SECURITY.md §4.5
 */
final class CsrfOriginException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::FORBIDDEN;
    }

    public static function missingOrigin(): self
    {
        return new self(
            message: 'Origin header is required for state-changing requests',
            errorCode: 'CSRF_ORIGIN_MISSING',
        );
    }

    public static function invalidOrigin(string $origin): self
    {
        return new self(
            message: 'Origin is not in the allowed list',
            errorCode: 'CSRF_ORIGIN_INVALID',
            context: ['origin' => $origin],
        );
    }
}
