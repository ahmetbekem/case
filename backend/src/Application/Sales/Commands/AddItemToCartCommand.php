<?php

declare(strict_types=1);

namespace Application\Sales\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class AddItemToCartCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $productId,
        public int $quantity,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'AddItemToCart';
    }
}
