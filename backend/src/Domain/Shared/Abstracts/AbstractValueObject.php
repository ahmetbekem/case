<?php

declare(strict_types=1);

namespace Domain\Shared\Abstracts;

use Domain\Shared\Contracts\ValueObjectInterface;

abstract readonly class AbstractValueObject implements ValueObjectInterface
{
    abstract public function value(): mixed;

    abstract public function equals(ValueObjectInterface $other): bool;

    public function jsonSerialize(): mixed
    {
        return $this->value();
    }
}
