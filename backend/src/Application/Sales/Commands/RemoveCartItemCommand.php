<?php

declare(strict_types=1);

namespace Application\Sales\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class RemoveCartItemCommand extends AbstractCommand
{
    public function __construct(
        ?string $userId,
        public ?string $guestSessionId,
        string $correlationId,
        string $userIp,
        public string $cartItemId,
    ) {
        if ($userId === null && $guestSessionId === null) {
            throw new \InvalidArgumentException('RemoveCartItemCommand requires userId or guestSessionId');
        }
        if ($userId !== null && $guestSessionId !== null) {
            throw new \InvalidArgumentException('RemoveCartItemCommand cannot have both userId and guestSessionId');
        }
        parent::__construct($userId ?? 'guest:' . $guestSessionId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'RemoveCartItem';
    }

    public function isGuest(): bool
    {
        return $this->guestSessionId !== null;
    }
}
