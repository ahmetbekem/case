<?php

declare(strict_types=1);

namespace Application\System\Queries;

use Application\Shared\Abstracts\AbstractQuery;

final readonly class GetAllSettingsQuery extends AbstractQuery
{
    public function __construct(
        string $userId,
        string $correlationId,
        /** true ise yalnızca is_public=true ayarlar döner (anonim erişim için) */
        public bool $publicOnly = false,
    ) {
        parent::__construct($userId, $correlationId);
    }
}
