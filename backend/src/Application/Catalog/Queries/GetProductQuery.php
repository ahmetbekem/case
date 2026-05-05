<?php

declare(strict_types=1);

namespace Application\Catalog\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class GetProductQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        public string $productId,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
