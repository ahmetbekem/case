<?php

declare(strict_types=1);

namespace Application\Shared\Contracts;

interface QueryHandlerInterface
{
    public function ask(QueryInterface $query): mixed;

    /**
     * @return class-string<QueryInterface>
     */
    public static function getHandledQueryClass(): string;
}
