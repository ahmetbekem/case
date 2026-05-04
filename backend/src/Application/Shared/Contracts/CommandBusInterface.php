<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

interface CommandBusInterface
{
    public function dispatch(CommandInterface $command): mixed;
}
