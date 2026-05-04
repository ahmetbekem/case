<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Audit;

use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Psr\Log\LoggerInterface;

/**
 * Bootstrap-time audit logger. Forwards every event to the standard
 * application logger so we still have visibility while developing
 * Identity / Catalog / Sales handlers — but it is **NOT** loss-intolerant
 * and MUST be replaced before production by the RabbitMQ-backed
 * AuditLogger (LOGGING_SERVICE.md §7.4) that lands in TASK 0.9.
 */
final class NullAuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function log(AuditEvent $event): void
    {
        $this->logger->info('audit-stub', $event->toArray());
    }
}
