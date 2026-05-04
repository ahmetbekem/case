<?php

declare(strict_types=1);

namespace Application\Shared\Abstracts;

use Application\Shared\Contracts\QueryHandlerInterface;
use Application\Shared\Contracts\QueryInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function ask(QueryInterface $query): mixed
    {
        // Read-only: no transaction, no mutation logging at base level.
        // Concrete handlers may add cache wrapping here in their own ask() override.
        return $this->doHandle($query);
    }

    abstract protected function doHandle(QueryInterface $query): mixed;
}
