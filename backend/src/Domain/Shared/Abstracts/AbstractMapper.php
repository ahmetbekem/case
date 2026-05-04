<?php

declare(strict_types=1);

namespace Domain\Shared\Abstracts;

use Domain\Shared\Contracts\EntityInterface;

abstract class AbstractMapper
{
    abstract public function toEntity(object $model): EntityInterface;

    /**
     * @return array<string, mixed>
     */
    abstract public function toPersistence(object $entity): array;
}
