<?php

declare(strict_types=1);

namespace Infrastructure\Currency\Console;

use Application\Currency\Commands\SyncCurrencyRatesCommand;
use Application\Shared\Contracts\CommandBusInterface;
use Illuminate\Console\Command;
use Ramsey\Uuid\Uuid;

/**
 * Manual / scheduled trigger for the daily currency sync.
 *
 * Usage:
 *   php artisan currency:sync-rates
 *
 * Scheduled:
 *   routes/console.php → Schedule::command('currency:sync-rates')->dailyAt('06:00')
 */
final class SyncCurrencyRatesArtisanCommand extends Command
{
    protected $signature = 'currency:sync-rates';

    protected $description = 'Fetch latest TRY-based rates from Frankfurter and upsert them';

    public function handle(CommandBusInterface $bus): int
    {
        try {
            /** @var array<int, mixed> $result */
            $result = $bus->dispatch(new SyncCurrencyRatesCommand(
                correlationId: 'cli-' . Uuid::uuid4()->toString(),
            ));
            $this->info('Synced ' . count($result) . ' exchange rate(s).');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
