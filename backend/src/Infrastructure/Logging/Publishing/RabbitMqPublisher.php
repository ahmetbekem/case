<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Publishing;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Sync publisher used directly by AuditLogger. Other categories
 * (application + access) go through QueuedLogPublisher → Laravel Queue
 * → a worker that calls publishAsync() from inside the queue handler.
 *
 * Connection + channel are lazy and reused across calls within the same
 * request; the destructor closes them. This is acceptable in a per-
 * request PHP-FPM setup; for long-running queue workers Laravel will
 * already keep the worker process alive so the same channel survives.
 *
 * Idempotent exchange declaration on first use means the publisher works
 * even before TASK 0.18 mounts infrastructure/rabbitmq/definitions.json
 * — bindings come later, but the exchange already exists.
 */
// NOTE: not `final` so unit tests can Mockery::mock() it directly. The
// AuditLogger fallback path (publish-failure-detection) is the only
// piece of behaviour we exercise without an actual broker.
class RabbitMqPublisher
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;
    private bool $confirmModeEnabled = false;

    /** @var array<string, true> */
    private array $declaredExchanges = [];

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
        private readonly string $vhost = '/',
        private readonly float $connectTimeout = 3.0,
        private readonly float $readWriteTimeout = 3.0,
    ) {
    }

    /**
     * Publish with mandatory + persistent + publisher confirm. Returns
     * true if the broker confirmed within $timeoutMs, false on timeout.
     * Connection / publish exceptions bubble up so the caller (AuditLogger)
     * can fall back to local file storage.
     *
     * @param array<string, mixed> $payload
     */
    public function publishWithConfirm(
        string $exchange,
        string $routingKey,
        array $payload,
        int $timeoutMs = 2000,
    ): bool {
        $channel = $this->channel();
        $this->declareExchangeOnce($channel, $exchange);

        if (!$this->confirmModeEnabled) {
            $channel->confirm_select();
            $this->confirmModeEnabled = true;
        }

        $message = $this->buildMessage($payload);
        $channel->basic_publish($message, $exchange, $routingKey, mandatory: true);

        try {
            $channel->wait_for_pending_acks($timeoutMs / 1000.0);
            return true;
        } catch (AMQPTimeoutException) {
            return false;
        }
    }

    /**
     * Fire-and-forget publish for non-critical categories. No confirm wait.
     *
     * @param array<string, mixed> $payload
     */
    public function publishAsync(
        string $exchange,
        string $routingKey,
        array $payload,
    ): void {
        $channel = $this->channel();
        $this->declareExchangeOnce($channel, $exchange);
        $channel->basic_publish($this->buildMessage($payload), $exchange, $routingKey);
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->connection = new AMQPStreamConnection(
                host: $this->host,
                port: $this->port,
                user: $this->user,
                password: $this->password,
                vhost: $this->vhost,
                connection_timeout: $this->connectTimeout,
                read_write_timeout: $this->readWriteTimeout,
            );
            $this->channel = $this->connection->channel();
        }
        return $this->channel;
    }

    private function declareExchangeOnce(AMQPChannel $channel, string $exchange): void
    {
        if (isset($this->declaredExchanges[$exchange])) {
            return;
        }
        // type=topic, passive=false, durable=true, auto_delete=false
        $channel->exchange_declare($exchange, 'topic', false, true, false);
        $this->declaredExchanges[$exchange] = true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildMessage(array $payload): AMQPMessage
    {
        return new AMQPMessage(
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ],
        );
    }

    public function __destruct()
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (\Throwable) {
            // Closing already-broken connection — nothing to do
        }
    }
}
