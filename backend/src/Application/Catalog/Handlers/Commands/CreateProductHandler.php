<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Commands;

use Application\Catalog\Commands\CreateProductCommand;
use Application\Catalog\DTOs\ProductDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Entities\Product;
use Domain\Catalog\Exceptions\DuplicateSkuException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\UserId;
use Domain\System\Services\SettingResolver;
use Psr\Log\LoggerInterface;

final class CreateProductHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SettingResolver $settingResolver,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return CreateProductCommand::class;
    }

    /**
     * @throws DuplicateSkuException
     */
    protected function doHandle(CommandInterface $command): ProductDTO
    {
        assert($command instanceof CreateProductCommand);

        if ($this->productRepository->existsBySku($command->sku)) {
            throw DuplicateSkuException::withSku($command->sku);
        }

        // Currency artık system setting'ten geliyor — admin form'da seçilmiyor
        // (ADR-016 single base currency).
        $baseCurrency = $this->settingResolver->getBaseCurrency();

        $product = new Product(
            id: ProductId::generate(),
            sku: $command->sku,
            name: $command->name,
            description: $command->description,
            price: new Money($command->price, $baseCurrency),
            stock: $command->stock,
            isActive: true,
            createdBy: UserId::fromString($command->getUserId()),
        );

        $this->productRepository->save($product);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'catalog.product_create',
            resource: 'product:' . $product->getId()->value(),
            module: 'catalog',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'sku' => $command->sku,
                'price' => $command->price,
                'currency' => $baseCurrency->value,
                'stock' => $command->stock,
            ],
        ));

        return ProductDTO::fromEntity($product);
    }
}
