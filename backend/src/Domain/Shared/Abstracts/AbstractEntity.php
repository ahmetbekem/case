<?php

declare(strict_types=1);

namespace Domain\Shared\Abstracts;

use Domain\Shared\Contracts\EntityInterface;
use Domain\Shared\Contracts\ValueObjectInterface;

abstract class AbstractEntity implements EntityInterface
{
    protected ?\DateTimeImmutable $createdAt = null;
    protected ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    protected function markAsUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    protected static function parseTimestamp(string|\DateTimeInterface $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        return new \DateTimeImmutable($value);
    }

    abstract public function getId(): ValueObjectInterface;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
