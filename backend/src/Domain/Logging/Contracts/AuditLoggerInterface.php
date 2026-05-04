<?php

declare(strict_types=1);

namespace Domain\Logging\Contracts;

use Domain\Logging\ValueObjects\AuditEvent;

/**
 * Loss-intolerant audit logger contract. Implementations MUST guarantee
 * delivery of every event (publisher confirms with local fallback per
 * LOGGING_SERVICE.md §4.1). Domain handlers depend on this interface;
 * Infrastructure provides the concrete RabbitMQ-backed publisher.
 *
 * A no-op implementation may be wired during early bootstrap (TASK 0.7)
 * before the real RabbitMQ pipeline lands in TASK 0.9.
 */
interface AuditLoggerInterface
{
    public function log(AuditEvent $event): void;
}
