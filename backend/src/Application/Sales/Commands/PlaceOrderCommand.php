<?php

declare(strict_types=1);

namespace Application\Sales\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * ADR-018 — Sipariş artık delivery + billing adres ID'leri zorunlu.
 * Frontend checkout sayfasında AddressSelector'dan seçilir;
 * billingAddressId null verilirse handler delivery ile aynı kabul
 * eder ("Fatura adresi teslimat ile aynı" UX kısayolu).
 */
final readonly class PlaceOrderCommand extends AbstractCommand
{
    public function __construct(
        string $userId,
        string $correlationId,
        string $userIp,
        public string $displayCurrency,
        public string $deliveryAddressId,
        public ?string $billingAddressId = null,
        public ?string $notes = null,
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'PlaceOrder';
    }
}
