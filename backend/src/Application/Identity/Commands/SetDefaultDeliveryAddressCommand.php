<?php

declare(strict_types=1);

namespace Application\Identity\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class SetDefaultDeliveryAddressCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $addressId,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'SetDefaultDeliveryAddress';
    }
}
