<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

interface QueryBusInterface
{
    public function ask(QueryInterface $query): mixed;
}
