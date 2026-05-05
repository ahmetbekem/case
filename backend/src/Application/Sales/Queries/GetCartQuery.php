<?php

declare(strict_types=1);

namespace Application\Sales\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class GetCartQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
