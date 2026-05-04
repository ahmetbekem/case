<?php

declare(strict_types=1);

namespace Application\Shared\Abstracts;

use Application\Shared\Contracts\CommandInterface;

abstract readonly class AbstractCommand implements CommandInterface
{
    public function __construct(
        protected string $userId,
        protected string $correlationId,
        protected string $userIp,
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

    public function getUserIp(): string
    {
        return $this->userIp;
    }

    abstract public function getCommandName(): string;
}
