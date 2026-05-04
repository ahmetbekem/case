<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

interface CommandInterface
{
    public function getUserId(): string;

    public function getCorrelationId(): string;

    public function getUserIp(): string;

    public function getCommandName(): string;
}
