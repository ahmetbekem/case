<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Commands;

use Application\Catalog\Commands\DeleteProductCommand;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\ProductNotFoundException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Psr\Log\LoggerInterface;

final class DeleteProductHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return DeleteProductCommand::class;
    }

    /**
     * @throws ProductNotFoundException
     */
    protected function doHandle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteProductCommand);

        $productId = ProductId::fromString($command->productId);

        // Confirm existence first so we throw ProductNotFoundException
        // (404) instead of silently no-op'ing on an unknown id.
        if ($this->productRepository->findById($productId) === null) {
            throw ProductNotFoundException::withId($command->productId);
        }

        $this->productRepository->softDelete($productId);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'catalog.product_delete',
            resource: 'product:' . $command->productId,
            module: 'catalog',
            result: 'success',
            correlationId: $command->getCorrelationId(),
        ));

        return null;
    }
}
