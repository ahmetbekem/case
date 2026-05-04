<?php

declare(strict_types=1);

namespace Domain\Shared\Exceptions;

use Domain\Shared\Constants\HttpStatus;

class InfrastructureException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::INTERNAL_SERVER_ERROR;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function withMessage(string $message, array $context = [], ?\Throwable $previous = null): static
    {
        return new static(
            message: $message,
            errorCode: 'INFRASTRUCTURE_FAILURE',
            context: $context,
            previous: $previous,
        );
    }
}
