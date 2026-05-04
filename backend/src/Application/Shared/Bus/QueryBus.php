<?php

declare(strict_types=1);

namespace Application\Shared\Bus;

use Application\Shared\Contracts\QueryBusInterface;
use Application\Shared\Contracts\QueryHandlerInterface;
use Application\Shared\Contracts\QueryInterface;
use Psr\Container\ContainerInterface;

final class QueryBus implements QueryBusInterface
{
    /**
     * @param array<class-string<QueryInterface>, class-string<QueryHandlerInterface>> $handlerMap
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $handlerMap,
    ) {
    }

    public function ask(QueryInterface $query): mixed
    {
        $queryClass = $query::class;

        if (!isset($this->handlerMap[$queryClass])) {
            throw new \RuntimeException(
                "No handler registered for query [{$queryClass}]. Add it to BusServiceProvider::\$queryHandlers.",
            );
        }

        $handlerClass = $this->handlerMap[$queryClass];
        $handler = $this->container->get($handlerClass);

        if (!$handler instanceof QueryHandlerInterface) {
            throw new \RuntimeException(
                "Resolved handler [{$handlerClass}] for query [{$queryClass}] does not implement QueryHandlerInterface.",
            );
        }

        return $handler->ask($query);
    }
}
