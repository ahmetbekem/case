<?php

declare(strict_types=1);

namespace Domain\Identity\ValueObjects;

use Domain\Shared\Abstracts\AbstractUuidValueObject;

/**
 * ADR-018 — Adres aggregate identity. UUID v4 format zorunluluğu
 * AbstractUuidValueObject tarafından doğrulanır.
 */
final readonly class AddressId extends AbstractUuidValueObject
{
}
