<?php

declare(strict_types=1);

namespace Domain\Shared\Contracts;

interface ValueObjectInterface extends \JsonSerializable
{
    public function value(): mixed;

    public function equals(self $other): bool;
}
