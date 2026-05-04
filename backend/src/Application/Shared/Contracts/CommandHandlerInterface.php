<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

interface CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed;

    /**
     * @return class-string<CommandInterface>
     */
    public static function getHandledCommandClass(): string;
}
