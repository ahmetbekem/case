<?php

declare(strict_types=1);

namespace Application\Sales\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class GetOrderQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        public string $orderId,
        public ?string $userRole = null,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
