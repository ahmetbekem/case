<?php

declare(strict_types=1);

namespace Application\Catalog\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * Partial update — every field nullable; null means "leave as-is" rather
 * than "set to null". The handler applies only the fields the client sent.
 */
final readonly class UpdateProductCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $productId,
        public ?string $name = null,
        public ?string $description = null,
        public ?float $price = null,
        public ?string $currency = null,
        public ?bool $isActive = null,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'UpdateProduct';
    }
}
