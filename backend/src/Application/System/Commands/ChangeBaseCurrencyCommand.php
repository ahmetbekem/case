<?php

declare(strict_types=1);

namespace Application\System\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * ADR-016 — base_currency setting değişimi standart UpdateSetting'den
 * farklı: tüm catalog.products fiyatları yeni base'e dönüştürülmek
 * zorunda; aksi takdirde "100 TRY ürün" "100 USD ürün"e dönüşür ve
 * anlamsızlaşır.
 *
 * Handler:
 *   1. Mevcut base'i okur; eşitse no-op
 *   2. ExchangeRate (oldBase → newBase) lookup; missing/stale → 422
 *   3. Tek transaction: products.price *= rate + currency=newBase,
 *      aktif cart'ları abandoned'a çek (snapshot mismatch koruması),
 *      base_currency setting'ini güncelle
 *   4. Audit: sistem.base_currency_changed (oldBase, newBase, rate,
 *      productsUpdated, cartsAbandoned)
 */
final readonly class ChangeBaseCurrencyCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $newBaseCurrency,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'ChangeBaseCurrency';
    }
}
