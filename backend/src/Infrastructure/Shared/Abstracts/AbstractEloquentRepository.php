<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Abstracts;

use Domain\Shared\Abstracts\AbstractMapper;
use Domain\Shared\Contracts\ValueObjectInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Common scaffolding for Eloquent-backed repositories. Concrete repositories
 * declare which model class to query and how to construct their VO id from a
 * raw string; everything else (mapper access, base query) lives here.
 *
 * @template TEntity of \Domain\Shared\Contracts\EntityInterface
 * @template TId of ValueObjectInterface
 * @template TModel of Model
 */
abstract class AbstractEloquentRepository
{
    public function __construct(
        protected readonly AbstractMapper $mapper,
    ) {
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function getModelClass(): string;

    abstract protected function createId(string $id): ValueObjectInterface;

    protected function query(): Builder
    {
        $modelClass = $this->getModelClass();
        return $modelClass::query();
    }
}
