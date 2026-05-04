<?php

declare(strict_types=1);

namespace Application\Shared\Abstracts;

abstract readonly class AbstractOutputDTO implements \JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
