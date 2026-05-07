<?php

declare(strict_types=1);

namespace Application\Identity\Queries;

use Application\Shared\Abstracts\AbstractQuery;

/**
 * Public statik veri sorgusu — auth gerekmez. AbstractQuery userId
 * zorunlu olduğundan controller 'anonymous' geçer.
 */
final readonly class GetCitiesQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
