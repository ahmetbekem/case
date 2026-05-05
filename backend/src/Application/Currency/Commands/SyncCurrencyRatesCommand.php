<?php

declare(strict_types=1);

namespace Application\Currency\Commands;

use Application\Shared\Abstracts\AbstractCommand;

/**
 * Daily scheduler trigger — fetches the latest TRY-based rates from
 * the configured provider and upserts them to currency.exchange_rates.
 *
 * Dispatched by routes/console.php's Schedule::call hook + the
 * `currency:sync-rates` artisan command for manual runs.
 */
final readonly class SyncCurrencyRatesCommand extends AbstractCommand
{
    public function __construct(
        string $userId = 'system',
        string $correlationId = '',
        string $userIp = 'system',
    ) {
        parent::__construct($userId, $correlationId, $userIp);
    }

    public function getCommandName(): string
    {
        return 'SyncCurrencyRates';
    }
}
