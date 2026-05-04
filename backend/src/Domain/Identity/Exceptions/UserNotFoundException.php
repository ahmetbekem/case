<?php

declare(strict_types=1);

namespace Domain\Identity\Exceptions;

use Domain\Shared\Exceptions\NotFoundException;

final class UserNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(
            message: 'User not found',
            errorCode: 'USER_NOT_FOUND',
            context: ['userId' => $id],
        );
    }

    public static function withEmail(string $email): self
    {
        return new self(
            message: 'User not found',
            errorCode: 'USER_NOT_FOUND',
            context: ['email' => $email],
        );
    }
}
