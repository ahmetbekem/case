<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Persistence;

use Application\Shared\Contracts\TransactionInterface;
use Illuminate\Database\DatabaseManager;

final class EloquentTransaction implements TransactionInterface
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {
    }

    public function run(callable $callback): mixed
    {
        return $this->db->connection()->transaction($callback);
    }
}
