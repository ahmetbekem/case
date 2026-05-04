<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

interface TransactionInterface
{
    /**
     * Run the callback inside a database transaction. Commits on return,
     * rolls back on any throwable. Returns whatever the callback returns.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
