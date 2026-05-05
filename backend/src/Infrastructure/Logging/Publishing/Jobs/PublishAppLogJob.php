<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Publishing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Infrastructure\Logging\Publishing\RabbitMqPublisher;

final class PublishAppLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 5; // seconds — exponential not needed for log shipping

    /**
     * @param array<string, mixed> $envelope
     */
    public function __construct(
        public readonly string $routingKey,
        public readonly array $envelope,
    ) {
    }

    public function handle(RabbitMqPublisher $publisher): void
    {
        $publisher->publishAsync('logs.exchange', $this->routingKey, $this->envelope);
    }
}
