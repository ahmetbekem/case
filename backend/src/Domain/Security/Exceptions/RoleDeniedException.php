<?php

declare(strict_types=1);

namespace Domain\Security\Exceptions;

use Domain\Shared\Constants\HttpStatus;
use Domain\Shared\Exceptions\DomainException;

final class RoleDeniedException extends DomainException
{
    public function getHttpStatusCode(): int
    {
        return HttpStatus::FORBIDDEN;
    }

    /**
     * @param array<int, string> $required
     */
    public static function forUser(string $userId, ?string $userRole, array $required): self
    {
        return new self(
            message: 'Insufficient role',
            errorCode: 'ROLE_DENIED',
            context: [
                'userId' => $userId,
                'userRole' => $userRole,
                'requiredRoles' => $required,
            ],
        );
    }
}
