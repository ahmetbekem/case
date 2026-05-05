<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Logging\Contracts\AuditLoggerInterface;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Logging\Audit\AuditFallbackWriter;
use Infrastructure\Logging\Audit\AuditLogger;
use Infrastructure\Logging\Audit\NullAuditLogger;
use Infrastructure\Logging\Publishing\NullQueuedLogPublisher;
use Infrastructure\Logging\Publishing\QueuedLogPublisher;
use Infrastructure\Logging\Publishing\RabbitMqPublisher;
use Psr\Log\LoggerInterface;

final class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('testing')) {
            $this->registerTestingBindings();
            return;
        }

        $this->registerProductionBindings();
    }

    /**
     * Test isolation: no real broker connection, no queue dispatches.
     * Same public contracts → consumers (Identity handlers,
     * AccessLogMiddleware, RabbitMqLogHandler) stay unchanged.
     */
    private function registerTestingBindings(): void
    {
        $this->app->singleton(AuditLoggerInterface::class, function ($app) {
            return new NullAuditLogger($app->make(LoggerInterface::class));
        });

        $this->app->singleton(QueuedLogPublisher::class, NullQueuedLogPublisher::class);
    }

    /**
     * Real pipeline:
     *   Identity handlers → AuditLogger → RabbitMqPublisher (sync confirms)
     *                                  ↘ AuditFallbackWriter on failure
     *
     *   Log::info(..., ['module'=>...]) → stack channel → 'rabbitmq' →
     *     RabbitMqLogHandler → QueuedLogPublisher → Job → RabbitMqPublisher
     *
     *   AccessLogMiddleware → QueuedLogPublisher → Job → RabbitMqPublisher
     */
    private function registerProductionBindings(): void
    {
        $this->app->singleton(RabbitMqPublisher::class, function () {
            return new RabbitMqPublisher(
                host: (string) env('RABBITMQ_HOST', '127.0.0.1'),
                port: (int) env('RABBITMQ_PORT', 5672),
                user: (string) env('RABBITMQ_USER', 'admin'),
                password: (string) env('RABBITMQ_PASSWORD', 'admin'),
                vhost: (string) env('RABBITMQ_VHOST', '/'),
            );
        });

        $this->app->singleton(AuditFallbackWriter::class, function () {
            return new AuditFallbackWriter(storage_path('logs/audit-fallback.log'));
        });

        $this->app->singleton(QueuedLogPublisher::class);

        $this->app->singleton(AuditLoggerInterface::class, AuditLogger::class);
    }
}
