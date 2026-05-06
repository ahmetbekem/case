<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Commands;

use Application\Sales\Commands\UpdateCartItemQuantityCommand;
use Application\Sales\DTOs\CartDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Entities\Cart;
use Domain\Sales\Exceptions\CartNotFoundException;
use Domain\Sales\ValueObjects\CartItemId;
use Domain\Sales\ValueObjects\GuestSessionId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class UpdateCartItemQuantityHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return UpdateCartItemQuantityCommand::class;
    }

    protected function doHandle(CommandInterface $command): CartDTO
    {
        assert($command instanceof UpdateCartItemQuantityCommand);

        $cart = $this->resolveCart($command);
        if ($cart === null) {
            throw CartNotFoundException::forUser($command->getUserId());
        }

        $cart->updateItemQuantity(
            CartItemId::fromString($command->cartItemId),
            $command->quantity,
        );

        $this->cartRepository->save($cart);

        $this->auditLogger->log(new AuditEvent(
            actor: $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'sales.cart_update_quantity',
            resource: 'cart:' . $cart->getId()->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'cartItemId' => $command->cartItemId,
                'newQuantity' => $command->quantity,
                'isGuest' => $command->isGuest(),
            ],
        ));

        return CartDTO::fromEntity($cart);
    }

    private function resolveCart(UpdateCartItemQuantityCommand $command): ?Cart
    {
        if ($command->isGuest()) {
            return $this->cartRepository->findActiveCartForGuestSession(
                GuestSessionId::fromString((string) $command->guestSessionId)
            );
        }
        return $this->cartRepository->findActiveCartForUser(
            UserId::fromString($command->getUserId())
        );
    }
}
