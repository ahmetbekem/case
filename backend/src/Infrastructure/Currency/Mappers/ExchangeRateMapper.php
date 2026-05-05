<?php

declare(strict_types=1);

namespace Infrastructure\Currency\Mappers;

use Domain\Currency\Entities\ExchangeRate;
use Domain\Shared\Abstracts\AbstractMapper;
use Infrastructure\Currency\Models\ExchangeRateModel;

final class ExchangeRateMapper extends AbstractMapper
{
    public function toEntity(object $model): ExchangeRate
    {
        assert($model instanceof ExchangeRateModel);

        return ExchangeRate::fromArray([
            'id' => $model->id,
            'baseCurrency' => $model->base_currency,
            'targetCurrency' => $model->target_currency,
            'rate' => (float) $model->rate,
            'rateDate' => $model->rate_date->format('Y-m-d'),
            'fetchedAt' => $model->fetched_at->format(\DateTimeInterface::ATOM),
            'provider' => $model->provider,
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(object $entity): array
    {
        assert($entity instanceof ExchangeRate);

        return [
            'id' => $entity->getId()->value(),
            'base_currency' => $entity->getBaseCurrency()->value,
            'target_currency' => $entity->getTargetCurrency()->value,
            'rate' => $entity->getRate(),
            'rate_date' => $entity->getRateDate()->format('Y-m-d'),
            'fetched_at' => $entity->getFetchedAt(),
            'provider' => $entity->getProvider(),
            'created_at' => $entity->getCreatedAt(),
        ];
    }
}
