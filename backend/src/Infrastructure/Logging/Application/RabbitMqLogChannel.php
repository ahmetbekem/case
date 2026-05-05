<?php

declare(strict_types=1);

namespace Infrastructure\Logging\Application;

use Monolog\Logger;

/**
 * Custom Monolog channel factory. Registered in config/logging.php as:
 *
 *   'rabbitmq' => [
 *       'driver' => 'custom',
 *       'via' => Infrastructure\Logging\Application\RabbitMqLogChannel::class,
 *       'level' => env('LOG_LEVEL', 'info'),
 *   ],
 *
 * Laravel calls __invoke($config) and uses the returned Logger instance.
 */
final class RabbitMqLogChannel
{
    /**
     * @param array<string, mixed> $config
     */
    public function __invoke(array $config): Logger
    {
        $level = $config['level'] ?? 'info';

        $logger = new Logger('rabbitmq');
        $logger->pushHandler(
            app(RabbitMqLogHandler::class, ['level' => \Monolog\Logger::toMonologLevel($level)]),
        );
        return $logger;
    }
}
