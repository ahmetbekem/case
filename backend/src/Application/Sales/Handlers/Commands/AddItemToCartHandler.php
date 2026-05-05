<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Commands;

use Application\Sales\Commands\AddItemToCartCommand;
use Application\Sales\DTOs\CartDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\ProductNotFoundException;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Entities\Cart;
use Domain\Sales\ValueObjects\CartId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class AddItemToCartHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return AddItemToCartCommand::class;
    }

    /**
     * @throws ProductNotFoundException
     */
    protected function doHandle(CommandInterface $command): CartDTO
    {
        assert($command instanceof AddItemToCartCommand);

        $userId = UserId::fromString($command->getUserId());
        $product = $this->productRepository->findById(ProductId::fromString($command->productId));

        if ($product === null) {
            throw ProductNotFoundException::withId($command->productId);
        }

        // Lazy-create the cart on first add — keeps anonymous users
        // out of the carts table, lets the partial unique index
        // (uniq_carts_user_active) own the "one active cart per user"
        // invariant via DB constraint instead of read-then-write race.
        $cart = $this->cartRepository->findActiveCartForUser($userId)
            ?? new Cart(id: CartId::generate(), userId: $userId);

        $cart->addItem(
            productId: $product->getId()->value(),
            quantity: $command->quantity,
            unitPrice: $product->getPrice(),
        );

        $this->cartRepository->save($cart);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'sales.cart_add_item',
            resource: 'cart:' . $cart->getId()->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'productId' => $product->getId()->value(),
                'quantity' => $command->quantity,
            ],
        ));

        return CartDTO::fromEntity($cart);
    }
}
