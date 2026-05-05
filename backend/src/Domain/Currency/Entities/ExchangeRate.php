<?php

declare(strict_types=1);

namespace Domain\Currency\Entities;

use Domain\Currency\ValueObjects\ExchangeRateId;
use Domain\Shared\Abstracts\AbstractEntity;
use Domain\Shared\ValueObjects\Currency;

/**
 * Immutable historical record of an exchange rate (1 base = N target on
 * a given date). No mutation methods — once written it never changes;
 * a new rate for the next day is a new row, not an update. Aligns with
 * DATABASE_SCHEMA §6.1 (no updated_at column).
 */
final class ExchangeRate extends AbstractEntity
{
    public function __construct(
        private readonly ExchangeRateId $id,
        private readonly Currency $baseCurrency,
        private readonly Currency $targetCurrency,
        private readonly float $rate,
        private readonly \DateTimeImmutable $rateDate,
        private readonly \DateTimeImmutable $fetchedAt,
        private readonly string $provider = 'frankfurter',
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        if ($baseCurrency === $targetCurrency) {
            throw new \InvalidArgumentException('Base and target currencies must differ');
        }
        if ($rate <= 0) {
            throw new \InvalidArgumentException('Exchange rate must be positive');
        }
        $this->createdAt = $createdAt;
    }

    public function getId(): ExchangeRateId
    {
        return $this->id;
    }

    public function getBaseCurrency(): Currency
    {
        return $this->baseCurrency;
    }

    public function getTargetCurrency(): Currency
    {
        return $this->targetCurrency;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function getRateDate(): \DateTimeImmutable
    {
        return $this->rateDate;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Convenience key for in-memory lookup tables (e.g. CurrencyConverter
     * accepts an array<string, ExchangeRate> keyed by this).
     */
    public function pairKey(): string
    {
        return $this->baseCurrency->value . '-' . $this->targetCurrency->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'baseCurrency' => $this->baseCurrency->value,
            'targetCurrency' => $this->targetCurrency->value,
            'rate' => $this->rate,
            'rateDate' => $this->rateDate->format('Y-m-d'),
            'fetchedAt' => $this->fetchedAt->format(\DateTimeInterface::ATOM),
            'provider' => $this->provider,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: ExchangeRateId::fromString((string) $data['id']),
            baseCurrency: Currency::from((string) $data['baseCurrency']),
            targetCurrency: Currency::from((string) $data['targetCurrency']),
            rate: (float) $data['rate'],
            rateDate: self::parseTimestamp($data['rateDate']),
            fetchedAt: self::parseTimestamp($data['fetchedAt']),
            provider: (string) ($data['provider'] ?? 'frankfurter'),
            createdAt: self::parseTimestamp($data['createdAt']),
        );
    }
}
