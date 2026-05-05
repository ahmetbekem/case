<?php

declare(strict_types=1);

namespace Application\Sales\Queries;

use Application\Shared\Abstracts\AbstractQuery;

/**
 * Customer mode: lists the calling user's own orders.
 * Admin mode: passing ?adminScope=true with role=admin lists everyone's.
 * Non-admin requesting adminScope is silently downgraded to own orders.
 */
final readonly class ListUserOrdersQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        public int $page = 1,
        public int $pageSize = 20,
        public ?string $userRole = null,
        public bool $adminScope = false,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
