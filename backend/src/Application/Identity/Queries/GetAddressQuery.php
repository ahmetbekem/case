<?php

declare(strict_types=1);

namespace Application\Identity\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class GetAddressQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        public string $addressId,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
