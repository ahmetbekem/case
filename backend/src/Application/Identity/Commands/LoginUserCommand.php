<?php

declare(strict_types=1);

namespace Application\Identity\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * ADR-017: guestSessionId opsiyonel — varsa LoginUserHandler login
 * sonrası MergeGuestCartCommand tetikler. Auth'tan bağımsızdır; merge
 * fail olsa bile login başarılı sayılır.
 */
final readonly class LoginUserCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $email,
        public string $password,
        public ?string $userAgent = null,
        public ?string $guestSessionId = null,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'LoginUser';
    }
}
