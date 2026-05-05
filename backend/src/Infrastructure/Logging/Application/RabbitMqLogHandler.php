<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Application;

use Infrastructure\Logging\Publishing\QueuedLogPublisher;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Ramsey\Uuid\Uuid;

/**
 * Monolog handler that routes Laravel app logs to RabbitMQ asynchronously
 * via the `logs` Redis queue. Each log call becomes one queued job — a
 * spike in error logs cannot back-pressure the user-facing request.
 *
 * Wired in config/logging.php as the `rabbitmq` channel via
 * RabbitMqLogChannel factory.
 */
final class RabbitMqLogHandler extends AbstractProcessingHandler
{
    /** @var array<int, string> */
    private const array SENSITIVE_KEYS = [
        'password', 'token', 'authorization', 'cookie', 'secret', 'api_key',
    ];

    public function __construct(
        private readonly QueuedLogPublisher $publisher,
        int|string|Level $level = Level::Info,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $module = isset($record->context['module']) ? (string) $record->context['module'] : 'unknown';

        $envelope = [
            'id' => Uuid::uuid4()->toString(),
            'category' => 'app',
            'publishedAt' => $record->datetime->format(\DateTimeInterface::RFC3339_EXTENDED),
            'source' => 'laravel-api',
            'version' => '1.0',
            'payload' => [
                'level' => $record->level->getName(),
                'module' => $module,
                'message' => $record->message,
                'context' => $this->sanitizeContext($record->context),
                'exception' => $this->extractException($record->context),
                'correlationId' => $record->context['correlationId'] ?? null,
                'userId' => $record->context['userId'] ?? null,
            ],
        ];

        $routingKey = "log.app.{$module}." . strtolower($record->level->getName());
        $this->publisher->dispatchAsync($routingKey, $envelope);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        // Strip the app-specific keys we already promoted to envelope fields
        unset($context['module'], $context['correlationId'], $context['userId'], $context['exception']);

        // Recursive scrub of obviously-sensitive keys
        return $this->scrub($context);
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }
        return $data;
    }

    private function isSensitive(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function extractException(array $context): ?array
    {
        if (!isset($context['exception']) || !$context['exception'] instanceof \Throwable) {
            return null;
        }
        $e = $context['exception'];
        return [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }
}
