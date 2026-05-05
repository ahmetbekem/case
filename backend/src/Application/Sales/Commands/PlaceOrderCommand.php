<?php

declare(strict_types=1);

namespace Application\Sales\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class PlaceOrderCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $displayCurrency,
        public ?string $notes = null,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'PlaceOrder';
    }
}
