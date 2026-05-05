<?php

declare(strict_types=1);

namespace Application\Currency\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class GetExchangeRateQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        public string $base,
        public string $target,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
