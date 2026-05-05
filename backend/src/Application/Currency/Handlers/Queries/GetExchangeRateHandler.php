<?php

declare(strict_types=1);

namespace Application\Currency\Handlers\Queries;

use Application\Currency\DTOs\ExchangeRateDTO;
use Application\Currency\Queries\GetExchangeRateQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use Domain\Currency\Exceptions\ExchangeRateNotFoundException;
use Domain\Currency\Exceptions\UnsupportedCurrencyException;
use Domain\Shared\ValueObjects\Currency;
use Psr\Log\LoggerInterface;

final class GetExchangeRateHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly ExchangeRateRepositoryInterface $repository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetExchangeRateQuery::class;
    }

    /**
     * @throws ExchangeRateNotFoundException
     * @throws UnsupportedCurrencyException
     */
    protected function doHandle(QueryInterface $query): ExchangeRateDTO
    {
        assert($query instanceof GetExchangeRateQuery);

        $base = $this->parseCurrency($query->base);
        $target = $this->parseCurrency($query->target);

        if ($base === $target) {
            return ExchangeRateDTO::identity($base->value);
        }

        $entity = $this->repository->findLatest($base, $target);

        if ($entity === null) {
            throw ExchangeRateNotFoundException::forPair($base->value, $target->value);
        }

        return ExchangeRateDTO::fromEntity($entity);
    }

    private function parseCurrency(string $code): Currency
    {
        $currency = Currency::tryFrom(strtoupper(trim($code)));
        if ($currency === null) {
            throw UnsupportedCurrencyException::withCode($code);
        }
        return $currency;
    }
}
