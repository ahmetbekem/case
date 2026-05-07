<?php

declare(strict_types=1);

namespace Domain\Currency\Exceptions;

use Domain\Shared\Exceptions\BusinessRuleException;

/**
 * ADR-016 — base currency değişimi gibi kritik repricing operasyonları
 * yalnızca taze kur ile yapılmalı. 7 günden eski rate ile çalışmak
 * piyasa hareketleri nedeniyle ürünleri ciddi ölçüde yanlış
 * fiyatlandırabilir. Caller önce currency:sync-rates çalıştırmalı.
 */
final class StaleExchangeRateException extends BusinessRuleException
{
    public static function olderThanDays(string $base, string $target, int $ageDays, int $maxAgeDays): self
    {
        return new self(
            message: sprintf(
                'Exchange rate for %s → %s is %d days old (max %d). Run currency sync first.',
                $base,
                $target,
                $ageDays,
                $maxAgeDays,
            ),
            errorCode: 'STALE_EXCHANGE_RATE',
            context: [
                'base' => $base,
                'target' => $target,
                'ageDays' => $ageDays,
                'maxAgeDays' => $maxAgeDays,
            ],
        );
    }
}
