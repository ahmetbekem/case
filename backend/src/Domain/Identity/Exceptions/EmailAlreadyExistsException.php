<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Exceptions\BusinessRuleException;

final class EmailAlreadyExistsException extends BusinessRuleException
{
    public static function withEmail(string $email): self
    {
        return new self(
            message: 'An account with this email already exists',
            errorCode: 'EMAIL_ALREADY_EXISTS',
            context: ['email' => $email],
        );
    }
}
