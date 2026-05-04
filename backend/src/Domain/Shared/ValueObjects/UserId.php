<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

use Domain\Shared\Abstracts\AbstractUuidValueObject;

/**
 * Cross-module user identifier.
 *
 * Lives in Shared because Identity, Sales, Catalog all reference users
 * (created_by, user_id, etc.) without depending on the Identity module.
 */
final readonly class UserId extends AbstractUuidValueObject
{
}
