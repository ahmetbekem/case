<?php

declare(strict_types=1);

namespace Application\Catalog\Commands;

use Application\Shared\Abstracts\AbstractCommand;

final readonly class DeleteProductCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $productId,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'DeleteProduct';
    }
}
