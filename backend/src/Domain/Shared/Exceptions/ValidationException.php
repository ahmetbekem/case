<?php

declare(strict_types=1);

namespace Domain\Shared\Exceptions;

use Domain\Shared\Constants\HttpStatus;

class ValidationException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::UNPROCESSABLE_ENTITY;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function withMessage(string $message, array $context = []): static
    {
        return new static(
            message: $message,
            errorCode: 'VALIDATION_FAILED',
            context: $context,
        );
    }
}
