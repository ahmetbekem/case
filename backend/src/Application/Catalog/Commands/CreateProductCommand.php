<?php

declare(strict_types=1);

namespace Application\Catalog\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class CreateProductCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $sku,
        public string $name,
        public ?string $description,
        public float $price,
        public int $stock,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'CreateProduct';
    }
}
