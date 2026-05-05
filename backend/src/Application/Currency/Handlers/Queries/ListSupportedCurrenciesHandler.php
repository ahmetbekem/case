<?php

declare(strict_types=1);

namespace Application\Currency\Handlers\Queries;

use Application\Currency\Queries\ListSupportedCurrenciesQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Shared\ValueObjects\Currency;
use Psr\Log\LoggerInterface;

final class ListSupportedCurrenciesHandler extends AbstractQueryHandler
{
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return ListSupportedCurrenciesQuery::class;
    }

    /**
     * @return array<int, array{code: string, symbol: string}>
     */
    protected function doHandle(QueryInterface $query): array
    {
        return array_map(
            fn (Currency $c) => ['code' => $c->value, 'symbol' => $c->symbol()],
            Currency::cases(),
        );
    }
}
