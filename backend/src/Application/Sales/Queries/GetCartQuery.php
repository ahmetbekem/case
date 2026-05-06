<?php

declare(strict_types=1);

namespace Application\Sales\Queries;

use Application\Shared\Abstracts\AbstractQuery;

/**
 * ADR-017: GetCart hem authenticated user hem de guest cart için
 * çalışır. userId veya guestSessionId — biri zorunlu, ikisi birden
 * olamaz. Parent::userId actor identifier'ı (guest için
 * `guest:<sessionId>`).
 */
final readonly class GetCartQuery extends AbstractQuery
{
    public function __construct(
        ?string $userId,
        public ?string $guestSessionId,
        string $correlationId,
    ) {
        if ($userId === null && $guestSessionId === null) {
            throw new \InvalidArgumentException('GetCartQuery requires userId or guestSessionId');
        }
        if ($userId !== null && $guestSessionId !== null) {
            throw new \InvalidArgumentException('GetCartQuery cannot have both userId and guestSessionId');
        }
        parent::__construct($userId ?? 'guest:' . $guestSessionId, $correlationId);
    }

    public function isGuest(): bool
    {
        return $this->guestSessionId !== null;
    }
}
