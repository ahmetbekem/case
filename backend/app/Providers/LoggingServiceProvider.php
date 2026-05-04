<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Logging\Contracts\AuditLoggerInterface;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Logging\Audit\NullAuditLogger;
use Psr\Log\LoggerInterface;

final class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bootstrap binding: events go to the standard Laravel logger
        // until TASK 0.9 swaps in the RabbitMQ-backed AuditLogger.
        $this->app->singleton(AuditLoggerInterface::class, function ($app) {
            return new NullAuditLogger($app->make(LoggerInterface::class));
        });
    }
}
