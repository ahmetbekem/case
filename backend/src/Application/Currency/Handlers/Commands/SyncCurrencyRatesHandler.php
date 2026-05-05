<?php

declare(strict_types=1);

namespace Application\Currency\Handlers\Commands;

use Application\Currency\Commands\SyncCurrencyRatesCommand;
use Application\Currency\DTOs\ExchangeRateDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Currency\Contracts\ExchangeRateProviderInterface;
use Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use Domain\Currency\Entities\ExchangeRate;
use Domain\Currency\ValueObjects\ExchangeRateId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Shared\ValueObjects\Currency;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;

final class SyncCurrencyRatesHandler extends AbstractCommandHandler
{
    private const string BASE_CURRENCY = 'TRY';
    private const int CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly ExchangeRateProviderInterface $provider,
        private readonly ExchangeRateRepositoryInterface $repository,
        private readonly CacheRepository $cache,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return SyncCurrencyRatesCommand::class;
    }

    /**
     * @return array<int, ExchangeRateDTO>
     */
    protected function doHandle(CommandInterface $command): array
    {
        assert($command instanceof SyncCurrencyRatesCommand);

        $base = Currency::from(self::BASE_CURRENCY);
        $targets = array_filter(
            Currency::cases(),
            fn (Currency $c) => $c !== $base,
        );

        $rawRates = $this->provider->fetchLatest($base, array_values($targets));
        $today = new \DateTimeImmutable('today');
        $now = new \DateTimeImmutable();
        $saved = [];

        foreach ($rawRates as $targetCode => $rate) {
            $target = Currency::tryFrom((string) $targetCode);
            if ($target === null || $target === $base) {
                continue;
            }

            $entity = new ExchangeRate(
                id: ExchangeRateId::generate(),
                baseCurrency: $base,
                targetCurrency: $target,
                rate: (float) $rate,
                rateDate: $today,
                fetchedAt: $now,
            );

            $this->repository->upsert($entity);
            $this->cache->put(
                $this->cacheKey($base, $target),
                (float) $rate,
                self::CACHE_TTL_SECONDS,
            );

            $saved[] = ExchangeRateDTO::fromEntity($entity);
        }

        $this->auditLogger->log(new AuditEvent(
            actor: 'system',
            actorIp: $command->getUserIp(),
            action: 'currency.sync_rates',
            resource: 'currency:rates',
            module: 'currency',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'base' => $base->value,
                'count' => count($saved),
                'rateDate' => $today->format('Y-m-d'),
            ],
        ));

        return $saved;
    }

    private function cacheKey(Currency $base, Currency $target): string
    {
        return "currency:rate:{$base->value}:{$target->value}";
    }
}
