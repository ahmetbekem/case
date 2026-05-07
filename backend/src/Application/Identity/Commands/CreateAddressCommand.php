<?php

declare(strict_types=1);

namespace Application\Identity\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class CreateAddressCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $title,
        public string $fullName,
        public string $phone,
        public string $city,
        public string $district,
        public ?string $neighborhood,
        public ?string $postalCode,
        public string $addressLine,
        public ?string $addressLine2,
        public bool $setAsDefaultDelivery = false,
        public bool $setAsDefaultBilling = false,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'CreateAddress';
    }
}
