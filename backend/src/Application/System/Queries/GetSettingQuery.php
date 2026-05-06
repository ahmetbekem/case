<?php

declare(strict_types=1);

namespace Application\System\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class GetSettingQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        public string $key,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
