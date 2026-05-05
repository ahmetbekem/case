<?php

declare(strict_types=1);

namespace Application\Currency\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class ListSupportedCurrenciesQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
