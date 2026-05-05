<?php

declare(strict_types=1);

namespace Infrastructure\Currency\Repositories;

use Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use Domain\Currency\Entities\ExchangeRate;
use Domain\Currency\ValueObjects\ExchangeRateId;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\Currency;
use Infrastructure\Currency\Mappers\ExchangeRateMapper;
use Infrastructure\Currency\Models\ExchangeRateModel;
use Infrastructure\Shared\Abstracts\AbstractEloquentRepository;

/**
 * @extends AbstractEloquentRepository<ExchangeRate, ExchangeRateId, ExchangeRateModel>
 */
final class EloquentExchangeRateRepository extends AbstractEloquentRepository implements ExchangeRateRepositoryInterface
{
    public function __construct(ExchangeRateMapper $mapper)
    {
        parent::__construct($mapper);
    }

    protected function getModelClass(): string
    {
        return ExchangeRateModel::class;
    }

    protected function createId(string $id): ValueObjectInterface
    {
        return ExchangeRateId::fromString($id);
    }

    public function findLatest(Currency $base, Currency $target): ?ExchangeRate
    {
        $model = $this->query()
            ->where('base_currency', $base->value)
            ->where('target_currency', $target->value)
            ->orderBy('rate_date', 'desc')
            ->first();

        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function loadAllLatest(Currency $base): array
    {
        // For each (base, target) pair pick the row with the latest rate_date.
        // PostgreSQL DISTINCT ON does this in a single query against the
        // idx_exchange_rates_pair_date_desc composite index.
        $models = $this->query()
            ->where('base_currency', $base->value)
            ->orderBy('target_currency')
            ->orderBy('rate_date', 'desc')
            ->get();

        // Group by target, keep only the first (latest) per group
        $latestPerTarget = [];
        foreach ($models as $model) {
            if (!isset($latestPerTarget[$model->target_currency])) {
                $latestPerTarget[$model->target_currency] = $model;
            }
        }

        return array_map(
            fn ($m) => $this->mapper->toEntity($m),
            array_values($latestPerTarget),
        );
    }

    /**
     * Idempotent insert keyed by (base_currency, target_currency, rate_date)
     * matches uniq_exchange_rates_pair_date so re-running today's sync
     * overwrites the rate cleanly without creating duplicates.
     */
    public function upsert(ExchangeRate $rate): void
    {
        $data = $this->mapper->toPersistence($rate);

        ExchangeRateModel::query()->updateOrInsert(
            attributes: [
                'base_currency' => $data['base_currency'],
                'target_currency' => $data['target_currency'],
                'rate_date' => $data['rate_date'],
            ],
            values: $data,
        );
    }
}
