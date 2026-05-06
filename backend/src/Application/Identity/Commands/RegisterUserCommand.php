<?php

declare(strict_types=1);

namespace Application\Identity\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * ADR-017: guestSessionId opsiyonel — varsa RegisterUserHandler
 * register sonrası MergeGuestCartCommand tetikler. Yeni kullanıcının
 * boş user cart'ı olduğundan merge handler senaryo 2 (transferOwnership)
 * yolunu izler.
 */
final readonly class RegisterUserCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $email,
        public string $password,
        public string $fullName,
        public ?string $guestSessionId = null,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'RegisterUser';
    }
}
