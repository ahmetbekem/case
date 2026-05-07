<?php

declare(strict_types=1);

namespace Application\System\Handlers\Commands;

use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Application\System\Commands\ChangeBaseCurrencyCommand;
use Application\System\DTOs\ChangeBaseCurrencyResultDTO;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use Domain\Currency\Exceptions\ExchangeRateNotFoundException;
use Domain\Currency\Exceptions\StaleExchangeRateException;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Shared\ValueObjects\Currency;
use Domain\System\Contracts\SettingRepositoryInterface;
use Domain\System\Exceptions\SettingNotFoundException;
use Domain\System\Services\SettingResolver;
use Domain\System\ValueObjects\SettingKey;
use Psr\Log\LoggerInterface;

/**
 * ADR-016 — base_currency setting değişiminin atomik işleyicisi.
 *
 * Akış:
 *   1) Mevcut base'i SettingResolver'dan oku — hedef ile aynıysa no-op
 *   2) (currentBase → newBase) kuru çöz: önce direkt, yoksa inverse,
 *      yoksa TRY-pivot. Bulunamazsa ExchangeRateNotFoundException.
 *   3) Stale guard: rateDate > 7 gün ise StaleExchangeRateException.
 *      currency:sync-rates job'unu çalıştırmadan kritik repricing
 *      kabul edilemez (ADR-017 SECURITY notu).
 *   4) Tek transaction:
 *        a) catalog.products bulk UPDATE (currency=old olanlar) → repriceAll
 *        b) sales.carts active'leri abandoned'a çek → cart_items mixed
 *           currency riskine düşmesin (snapshot eski para biriminde kalır)
 *        c) base_currency setting.value güncelle (CachedSettingRepository
 *           cache'i save'de invalidate eder)
 *   5) Audit event: system.base_currency_changed
 *
 * Hatalar (transaction rollback):
 *   - SettingNotFoundException (404): base_currency seed edilmemiş
 *   - ExchangeRateNotFoundException (404): kur tablosunda eşleşme yok
 *   - StaleExchangeRateException (409): kur eski → sync gerekli
 */
final class ChangeBaseCurrencyHandler extends AbstractCommandHandler
{
    /** Kur staleness eşiği — bu süreden eski rate ile reprice yasaktır. */
    private const int MAX_RATE_AGE_DAYS = 7;

    /** TRY pivot çözümü için kanonik base — SyncCurrencyRatesHandler ile uyumlu. */
    private const string PIVOT_CURRENCY = 'TRY';

    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository,
        private readonly SettingResolver $settingResolver,
        private readonly ExchangeRateRepositoryInterface $rateRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return ChangeBaseCurrencyCommand::class;
    }

    protected function doHandle(CommandInterface $command): ChangeBaseCurrencyResultDTO
    {
        assert($command instanceof ChangeBaseCurrencyCommand);

        $newBase = Currency::tryFrom($command->newBaseCurrency)
            ?? throw new \InvalidArgumentException(
                "Unsupported currency code: {$command->newBaseCurrency}"
            );
        $currentBase = $this->settingResolver->getBaseCurrency();

        if ($currentBase === $newBase) {
            // Idempotent — UI base_currency'yi aynı değere "kaydet" ederse
            return new ChangeBaseCurrencyResultDTO(
                fromCurrency: $currentBase->value,
                toCurrency: $newBase->value,
                rate: 1.0,
                rateDate: (new \DateTimeImmutable())->format('Y-m-d'),
                productsUpdated: 0,
                cartsAbandoned: 0,
            );
        }

        // Setting var mı? Yoksa burada açıkça hatayı görelim — sessiz no-op olmasın
        $setting = $this->settingRepository->findByKey(SettingKey::BASE_CURRENCY);
        if ($setting === null) {
            throw SettingNotFoundException::withKey(SettingKey::BASE_CURRENCY);
        }

        [$rate, $rateDate] = $this->resolveRate($currentBase, $newBase);

        $result = $this->transaction->run(
            function () use ($currentBase, $newBase, $rate, $setting): array {
                $productsUpdated = $this->productRepository->repriceAll(
                    from: $currentBase,
                    to: $newBase,
                    rate: $rate,
                );
                $cartsAbandoned = $this->cartRepository->abandonAllActive();

                $setting->changeValue($newBase->value);
                $this->settingRepository->save($setting);

                return [
                    'productsUpdated' => $productsUpdated,
                    'cartsAbandoned' => $cartsAbandoned,
                ];
            }
        );

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'system.base_currency_changed',
            resource: 'setting:' . SettingKey::BASE_CURRENCY,
            module: 'system',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'fromCurrency' => $currentBase->value,
                'toCurrency' => $newBase->value,
                'rate' => $rate,
                'rateDate' => $rateDate->format('Y-m-d'),
                'productsUpdated' => $result['productsUpdated'],
                'cartsAbandoned' => $result['cartsAbandoned'],
            ],
        ));

        return new ChangeBaseCurrencyResultDTO(
            fromCurrency: $currentBase->value,
            toCurrency: $newBase->value,
            rate: $rate,
            rateDate: $rateDate->format('Y-m-d'),
            productsUpdated: $result['productsUpdated'],
            cartsAbandoned: $result['cartsAbandoned'],
        );
    }

    /**
     * (oldBase → newBase) kuru çöz. Sırayla:
     *   1) findLatest(old, new) — direkt
     *   2) findLatest(new, old) — inverse, 1 / rate
     *   3) findLatest(TRY, new) / findLatest(TRY, old) — TRY pivot
     *
     * @return array{0: float, 1: \DateTimeImmutable}  [rate, rateDate]
     * @throws ExchangeRateNotFoundException
     * @throws StaleExchangeRateException
     */
    private function resolveRate(Currency $from, Currency $to): array
    {
        $direct = $this->rateRepository->findLatest($from, $to);
        if ($direct !== null) {
            $this->assertFresh($direct->getRateDate(), $from->value, $to->value);
            return [$direct->getRate(), $direct->getRateDate()];
        }

        $inverse = $this->rateRepository->findLatest($to, $from);
        if ($inverse !== null) {
            $this->assertFresh($inverse->getRateDate(), $from->value, $to->value);
            return [1.0 / $inverse->getRate(), $inverse->getRateDate()];
        }

        // TRY pivot — yalnızca from/to TRY değilse anlamlı
        $pivot = Currency::from(self::PIVOT_CURRENCY);
        if ($from !== $pivot && $to !== $pivot) {
            $pivotToFrom = $this->rateRepository->findLatest($pivot, $from);
            $pivotToTo = $this->rateRepository->findLatest($pivot, $to);
            if ($pivotToFrom !== null && $pivotToTo !== null) {
                $this->assertFresh($pivotToFrom->getRateDate(), $from->value, $to->value);
                $this->assertFresh($pivotToTo->getRateDate(), $from->value, $to->value);
                $rate = $pivotToTo->getRate() / $pivotToFrom->getRate();
                $olderDate = $pivotToFrom->getRateDate() < $pivotToTo->getRateDate()
                    ? $pivotToFrom->getRateDate()
                    : $pivotToTo->getRateDate();
                return [$rate, $olderDate];
            }
        }

        throw ExchangeRateNotFoundException::forPair($from->value, $to->value);
    }

    private function assertFresh(\DateTimeImmutable $rateDate, string $from, string $to): void
    {
        $ageDays = $rateDate->diff(new \DateTimeImmutable())->days;
        if ($ageDays !== false && $ageDays > self::MAX_RATE_AGE_DAYS) {
            throw StaleExchangeRateException::olderThanDays(
                base: $from,
                target: $to,
                ageDays: (int) $ageDays,
                maxAgeDays: self::MAX_RATE_AGE_DAYS,
            );
        }
    }
}
