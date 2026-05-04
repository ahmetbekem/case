<?php

declare(strict_types=1);

namespace Application\Shared\Bus;

use Application\Shared\Contracts\CommandBusInterface;
use Application\Shared\Contracts\CommandHandlerInterface;
use Application\Shared\Contracts\CommandInterface;
use Psr\Container\ContainerInterface;

final class CommandBus implements CommandBusInterface
{
    /**
     * @param array<class-string<CommandInterface>, class-string<CommandHandlerInterface>> $handlerMap
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $handlerMap,
    ) {
    }

    public function dispatch(CommandInterface $command): mixed
    {
        $commandClass = $command::class;

        if (!isset($this->handlerMap[$commandClass])) {
            throw new \RuntimeException(
                "No handler registered for command [{$commandClass}]. Add it to BusServiceProvider::\$commandHandlers.",
            );
        }

        $handlerClass = $this->handlerMap[$commandClass];
        $handler = $this->container->get($handlerClass);

        if (!$handler instanceof CommandHandlerInterface) {
            throw new \RuntimeException(
                "Resolved handler [{$handlerClass}] for command [{$commandClass}] does not implement CommandHandlerInterface.",
            );
        }

        return $handler->handle($command);
    }
}
