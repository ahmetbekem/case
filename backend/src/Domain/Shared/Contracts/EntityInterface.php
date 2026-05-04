<?php

declare(strict_types=1);

namespace Domain\Shared\Contracts;

interface EntityInterface
{
    public function getId(): ValueObjectInterface;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
