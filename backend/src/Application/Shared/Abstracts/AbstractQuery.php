<?php

declare(strict_types=1);

namespace Application\Shared\Abstracts;

use Application\Shared\Contracts\QueryInterface;

abstract readonly class AbstractQuery implements QueryInterface
{
    public function __construct(
        protected string $userId,
        protected string $correlationId,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }
}
