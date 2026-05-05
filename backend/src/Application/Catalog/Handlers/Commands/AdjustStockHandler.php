<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Commands;

use Application\Catalog\Commands\AdjustStockCommand;
use Application\Catalog\DTOs\ProductDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\InsufficientStockException;
use Domain\Catalog\Exceptions\ProductNotFoundException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Psr\Log\LoggerInterface;

final class AdjustStockHandler extends AbstractCommandHandler
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
        return AdjustStockCommand::class;
    }

    /**
     * @throws ProductNotFoundException
     * @throws InsufficientStockException
     */
    protected function doHandle(CommandInterface $command): ProductDTO
    {
        assert($command instanceof AdjustStockCommand);

        if ($command->delta === 0) {
            throw new \InvalidArgumentException('Stock delta cannot be zero');
        }

        $productId = ProductId::fromString($command->productId);
        $product = $this->productRepository->findById($productId);

        if ($product === null) {
            throw ProductNotFoundException::withId($command->productId);
        }

        $stockBefore = $product->getStock();

        if ($command->delta > 0) {
            $product->increaseStock($command->delta);
        } else {
            $product->decreaseStock(abs($command->delta));
        }

        $this->productRepository->save($product);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'catalog.product_adjust_stock',
            resource: 'product:' . $command->productId,
            module: 'catalog',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'delta' => $command->delta,
                'stockBefore' => $stockBefore,
                'stockAfter' => $product->getStock(),
            ],
        ));

        return ProductDTO::fromEntity($product);
    }
}
