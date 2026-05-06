<?php

declare(strict_types=1);

namespace Application\Sales\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class ClearCartCommand extends AbstractCommand
{
    public function __construct(
        ?string $userId,
        public ?string $guestSessionId,
        string $correlationId,
        string $userIp,
    ) {
        if ($userId === null && $guestSessionId === null) {
            throw new \InvalidArgumentException('ClearCartCommand requires userId or guestSessionId');
        }
        if ($userId !== null && $guestSessionId !== null) {
            throw new \InvalidArgumentException('ClearCartCommand cannot have both userId and guestSessionId');
        }
        parent::__construct($userId ?? 'guest:' . $guestSessionId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'ClearCart';
    }

    public function isGuest(): bool
    {
        return $this->guestSessionId !== null;
    }
}
