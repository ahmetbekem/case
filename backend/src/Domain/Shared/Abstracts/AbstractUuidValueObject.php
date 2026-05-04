<?php

declare(strict_types=1);

namespace Domain\Shared\Abstracts;

use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\Exceptions\InvalidUuidException;
use Ramsey\Uuid\Uuid;

abstract readonly class AbstractUuidValueObject extends AbstractValueObject
{
    final protected function __construct(private string $value)
    {
        if (!Uuid::isValid($value)) {
            throw InvalidUuidException::forValue($value);
        }
    }

    public static function generate(): static
    {
        return new static(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(ValueObjectInterface $other): bool
    {
        return $other instanceof static && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
