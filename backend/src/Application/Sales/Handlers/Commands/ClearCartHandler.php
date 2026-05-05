<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Commands;

use Application\Sales\Commands\ClearCartCommand;
use Application\Sales\DTOs\CartDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Entities\Cart;
use Domain\Sales\ValueObjects\CartId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class ClearCartHandler extends AbstractCommandHandler
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
        return ClearCartCommand::class;
    }

    protected function doHandle(CommandInterface $command): CartDTO
    {
        assert($command instanceof ClearCartCommand);

        $userId = UserId::fromString($command->getUserId());
        $cart = $this->cartRepository->findActiveCartForUser($userId);

        // Idempotent — if there's no active cart, treat the request as
        // already-empty and return a fresh DTO so callers don't have to
        // special-case the missing-cart scenario.
        if ($cart === null) {
            return CartDTO::fromEntity(new Cart(
                id: CartId::generate(),
                userId: $userId,
            ));
        }

        $cart->clear();
        $this->cartRepository->save($cart);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'sales.cart_clear',
            resource: 'cart:' . $cart->getId()->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
        ));

        return CartDTO::fromEntity($cart);
    }
}
