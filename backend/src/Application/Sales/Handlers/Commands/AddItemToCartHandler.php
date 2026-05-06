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
use Domain\Sales\ValueObjects\GuestSessionId;
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

        $product = $this->productRepository->findById(ProductId::fromString($command->productId));
        if ($product === null) {
            throw ProductNotFoundException::withId($command->productId);
        }

        // Lazy-create cart on first add — partial unique index
        // (uniq_carts_user_active / uniq_carts_guest_active) owns the
        // "one active cart" invariant via DB constraint.
        $cart = $this->resolveCart($command);

        $cart->addItem(
            productId: $product->getId()->value(),
            productName: $product->getName(),
            productSku: $product->getSku(),
            quantity: $command->quantity,
            unitPrice: $product->getPrice(),
        );

        $this->cartRepository->save($cart);

        $this->auditLogger->log(new AuditEvent(
            actor: $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'sales.cart_add_item',
            resource: 'cart:' . $cart->getId()->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'productId' => $product->getId()->value(),
                'quantity' => $command->quantity,
                'isGuest' => $command->isGuest(),
            ],
        ));

        return CartDTO::fromEntity($cart);
    }

    private function resolveCart(AddItemToCartCommand $command): Cart
    {
        if ($command->isGuest()) {
            $sessionId = GuestSessionId::fromString((string) $command->guestSessionId);
            return $this->cartRepository->findActiveCartForGuestSession($sessionId)
                ?? Cart::createForGuest($sessionId);
        }

        $userId = UserId::fromString($command->getUserId());
        return $this->cartRepository->findActiveCartForUser($userId)
            ?? Cart::createForUser($userId);
    }
}
