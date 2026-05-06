<?php

declare(strict_types=1);

namespace Domain\Sales\ValueObjects;

use Domain\Shared\Abstracts\AbstractUuidValueObject;

/**
 * ADR-017 — Guest cart identifier. Cookie'de saklanır
 * (guest_cart_token), 30 gün TTL. Login sonrası user_id'ye merge
 * edilir ve session sonlandırılır.
 *
 * UUID v4 format zorunluluğu AbstractUuidValueObject tarafından
 * doğrulanır.
 */
final readonly class GuestSessionId extends AbstractUuidValueObject
{
}
