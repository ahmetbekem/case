<?php

declare(strict_types=1);

namespace Domain\Shared\Contracts;

/**
 * Marker interface for module-specific repository contracts.
 *
 * Each module declares its own repository interface (e.g. ProductRepositoryInterface)
 * extending this and adding the methods it needs. Concrete query methods stay in
 * the module-level interface so the Domain layer never sees Eloquent.
 *
 * @template TEntity of EntityInterface
 * @template TId of ValueObjectInterface
 */
interface RepositoryInterface
{
}
