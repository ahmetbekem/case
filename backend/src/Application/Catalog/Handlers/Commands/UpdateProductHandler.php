<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Commands;

use Application\Catalog\Commands\UpdateProductCommand;
use Application\Catalog\DTOs\ProductDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\ProductNotFoundException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Shared\ValueObjects\Money;
use Psr\Log\LoggerInterface;

final class UpdateProductHandler extends AbstractCommandHandler
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
        return UpdateProductCommand::class;
    }

    /**
     * @throws ProductNotFoundException
     */
    protected function doHandle(CommandInterface $command): ProductDTO
    {
        assert($command instanceof UpdateProductCommand);

        $productId = ProductId::fromString($command->productId);
        $product = $this->productRepository->findById($productId);

        if ($product === null) {
            throw ProductNotFoundException::withId($command->productId);
        }

        // Track which fields actually changed for the audit metadata
        $changes = [];

        if ($command->name !== null) {
            $product->rename($command->name);
            $changes['name'] = $command->name;
        }
        if ($command->description !== null) {
            $product->changeDescription($command->description);
            $changes['description'] = $command->description;
        }
        if ($command->price !== null) {
            // Currency değişmez — base_currency system setting'inden geliyor
            // (ADR-016). Mevcut entity currency'sini koru.
            $product->changePrice(new Money(
                amount: $command->price,
                currency: $product->getPrice()->currency,
            ));
            $changes['price'] = $product->getPrice()->amount;
        }
        if ($command->isActive !== null) {
            $command->isActive ? $product->activate() : $product->deactivate();
            $changes['isActive'] = $command->isActive;
        }

        $this->productRepository->save($product);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'catalog.product_update',
            resource: 'product:' . $product->getId()->value(),
            module: 'catalog',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: ['changes' => $changes],
        ));

        return ProductDTO::fromEntity($product);
    }
}
