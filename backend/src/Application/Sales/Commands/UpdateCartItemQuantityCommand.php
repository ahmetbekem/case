<?php

declare(strict_types=1);

namespace Application\Sales\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class UpdateCartItemQuantityCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $cartItemId,
        public int $quantity,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'UpdateCartItemQuantity';
    }
}
