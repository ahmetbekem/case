<?php

declare(strict_types=1);

namespace Application\Identity\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class ListUserAddressesQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
