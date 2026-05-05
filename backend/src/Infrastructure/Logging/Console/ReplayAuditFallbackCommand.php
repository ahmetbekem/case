<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Console;

use Illuminate\Console\Command;
use Infrastructure\Logging\Audit\AuditFallbackWriter;
use Infrastructure\Logging\Publishing\RabbitMqPublisher;

/**
 * Replays audit envelopes from the fallback NDJSON file back to RabbitMQ.
 *
 * Operational flow:
 *   1. RabbitMQ comes back online after an outage.
 *   2. Op runs `php artisan logging:replay-audit-fallback`.
 *   3. Every line gets re-published with a `replayed` marker.
 *   4. If ALL re-publishes confirmed, the file is cleared. If any failed
 *      we leave the file in place so the next run can retry.
 *
 * Exit codes: 0 on full success, 1 on any failure.
 */
final class ReplayAuditFallbackCommand extends Command
{
    protected $signature = 'logging:replay-audit-fallback {--dry-run : Print without publishing}';

    protected $description = 'Re-publish audit-fallback.log entries to RabbitMQ';

    public function handle(AuditFallbackWriter $writer, RabbitMqPublisher $publisher): int
    {
        if (!$writer->exists()) {
            $this->info('No fallback file present at ' . $writer->getPath());
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $replayed = 0;
        $failed = 0;

        foreach ($writer->readEntries() as $envelope) {
            $routingKey = $this->originalRoutingKey($envelope) . '.replayed';

            if ($dryRun) {
                $this->line("[dry-run] {$routingKey} {$envelope['id']}");
                $replayed++;
                continue;
            }

            try {
                $confirmed = $publisher->publishWithConfirm(
                    exchange: 'logs.exchange',
                    routingKey: $routingKey,
                    payload: $envelope,
                    timeoutMs: 5000,
                );
                if ($confirmed) {
                    $replayed++;
                } else {
                    $failed++;
                    $this->warn("Confirm timeout for {$envelope['id']}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Publish failed for {$envelope['id']}: {$e->getMessage()}");
            }
        }

        $this->info("Replayed: {$replayed}, Failed: {$failed}");

        if ($failed === 0 && !$dryRun) {
            $writer->clear();
            $this->info("Fallback file cleared.");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Reconstruct the original audit routing key so consumers can still
     * subscribe to the same `log.audit.{module}.{action}` topic but tell
     * a real-time event from a recovered one via the `.replayed` suffix.
     *
     * @param array<string, mixed> $envelope
     */
    private function originalRoutingKey(array $envelope): string
    {
        $payload = $envelope['payload'] ?? [];
        $module = (string) ($payload['module'] ?? 'unknown');
        $action = str_replace('.', '_', (string) ($payload['action'] ?? 'unknown'));
        return "log.audit.{$module}.{$action}";
    }
}
