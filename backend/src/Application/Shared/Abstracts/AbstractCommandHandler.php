<?php

declare(strict_types=1);

namespace Application\Shared\Abstracts;

use Application\Shared\Contracts\CommandHandlerInterface;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        protected readonly TransactionInterface $transaction,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        $startedAt = microtime(true);

        try {
            $result = $this->transaction->run(fn () => $this->doHandle($command));

            $this->logger->info('Command handled', [
                'command' => $command->getCommandName(),
                'durationMs' => (int) ((microtime(true) - $startedAt) * 1000),
                'correlationId' => $command->getCorrelationId(),
                'userId' => $command->getUserId(),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Command failed', [
                'command' => $command->getCommandName(),
                'errorClass' => $e::class,
                'error' => $e->getMessage(),
                'durationMs' => (int) ((microtime(true) - $startedAt) * 1000),
                'correlationId' => $command->getCorrelationId(),
                'userId' => $command->getUserId(),
            ]);
            throw $e;
        }
    }

    abstract protected function doHandle(CommandInterface $command): mixed;
}
