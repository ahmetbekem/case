<?php

declare(strict_types=1);

namespace Application\Catalog\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class ListProductsQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        public int $page = 1,
        public int $pageSize = 20,
        public ?string $search = null,
        public bool $activeOnly = true,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
