<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Publishing;

use Infrastructure\Logging\Publishing\Jobs\PublishAccessLogJob;
use Infrastructure\Logging\Publishing\Jobs\PublishAppLogJob;

/**
 * Async log publisher — application + access categories. Pushes a job
 * onto the `logs` Redis queue; a queue worker (queue-worker container in
 * docker-compose) picks it up and calls RabbitMqPublisher::publishAsync.
 *
 * Trade-off vs sync publish: ~ms latency saved on the user-facing
 * request, at the cost of "best effort" delivery (a Redis outage drops
 * pending log jobs). That fits LOGGING_SERVICE.md §4 SLA: only audit
 * logs are loss-intolerant; app + access are loss-tolerant.
 *
 * This class is not `final` so a NullQueuedLogPublisher subclass can
 * be bound during the test suite — keeps tests RabbitMQ-free.
 */
class QueuedLogPublisher
{
    /**
     * @param array<string, mixed> $envelope
     */
    public function dispatchAsync(string $routingKey, array $envelope): void
    {
        $category = $envelope['category'] ?? null;

        match ($category) {
            'app'    => PublishAppLogJob::dispatch($routingKey, $envelope)->onQueue('logs'),
            'access' => PublishAccessLogJob::dispatch($routingKey, $envelope)->onQueue('logs'),
            default  => null, // unknown category — silently drop (best effort)
        };
    }
}
