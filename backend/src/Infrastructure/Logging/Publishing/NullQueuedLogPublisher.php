<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Publishing;

/**
 * Test-time stand-in for QueuedLogPublisher. The Pest suite must not
 * require RabbitMQ to be running, and shouldn't dispatch real queue jobs
 * either, so LoggingServiceProvider binds this class in the testing
 * environment. Same public API → AccessLogMiddleware + RabbitMqLogHandler
 * stay unchanged.
 */
final class NullQueuedLogPublisher extends QueuedLogPublisher
{
    /**
     * @param array<string, mixed> $envelope
     */
    public function dispatchAsync(string $routingKey, array $envelope): void
    {
        // no-op
    }
}
