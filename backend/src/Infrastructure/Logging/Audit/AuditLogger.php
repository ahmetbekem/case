<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Audit;

use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Infrastructure\Logging\Publishing\RabbitMqPublisher;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Loss-intolerant audit logger. Sync publish to RabbitMQ with publisher
 * confirms; on confirm-timeout OR any throwable from the AMQP layer the
 * envelope is appended to the fallback NDJSON file and a `critical`
 * application log is emitted so on-call sees the gap.
 *
 * The replay command (logging:replay-audit-fallback) drains the file
 * back into RabbitMQ once the broker is healthy again.
 *
 * This replaces NullAuditLogger from TASK 0.7 — every Identity handler
 * already injects AuditLoggerInterface, so the swap is transparent to
 * them. Wired up by LoggingServiceProvider in non-testing environments.
 */
final class AuditLogger implements AuditLoggerInterface
{
    private const string EXCHANGE = 'logs.exchange';
    private const int CONFIRM_TIMEOUT_MS = 2000;
    private const string SOURCE = 'laravel-api';
    private const string ENVELOPE_VERSION = '1.0';

    public function __construct(
        private readonly RabbitMqPublisher $publisher,
        private readonly AuditFallbackWriter $fallback,
        private readonly LoggerInterface $internalLogger,
    ) {
    }

    public function log(AuditEvent $event): void
    {
        $envelope = $this->envelope($event);
        $routingKey = $this->routingKey($event);

        try {
            $confirmed = $this->publisher->publishWithConfirm(
                exchange: self::EXCHANGE,
                routingKey: $routingKey,
                payload: $envelope,
                timeoutMs: self::CONFIRM_TIMEOUT_MS,
            );

            if (!$confirmed) {
                $this->fallback->write($envelope);
                $this->internalLogger->critical('Audit log not confirmed by RabbitMQ — fell back to file', [
                    'auditId' => $envelope['id'],
                    'routingKey' => $routingKey,
                    'fallbackPath' => $this->fallback->getPath(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->fallback->write($envelope);
            $this->internalLogger->critical('Audit log publish threw — fell back to file', [
                'auditId' => $envelope['id'],
                'routingKey' => $routingKey,
                'errorClass' => $e::class,
                'error' => $e->getMessage(),
                'fallbackPath' => $this->fallback->getPath(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(AuditEvent $event): array
    {
        return [
            'id' => Uuid::uuid4()->toString(),
            'category' => 'audit',
            'publishedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            'source' => self::SOURCE,
            'version' => self::ENVELOPE_VERSION,
            'payload' => $event->toArray(),
        ];
    }

    /**
     * Routing keys follow `log.audit.{module}.{action}` so consumers can
     * subscribe to a slice (e.g. `log.audit.security.*`). Dots inside the
     * action are flattened to underscores so the AMQP topic match doesn't
     * accidentally split a single action across multiple key segments.
     */
    private function routingKey(AuditEvent $event): string
    {
        $action = str_replace('.', '_', $event->action);
        return "log.audit.{$event->module}.{$action}";
    }
}
