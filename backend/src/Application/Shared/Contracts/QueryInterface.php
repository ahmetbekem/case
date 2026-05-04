<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

interface QueryInterface
{
    public function getUserId(): string;

    public function getCorrelationId(): string;
}
