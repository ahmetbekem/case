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
use Domain\Sales\ValueObjects\GuestSessionId;
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

        $cart = $this->resolveCart($command);

        // Idempotent — if there's no active cart, treat the request as
        // already-empty and return a fresh DTO so callers don't have to
        // special-case the missing-cart scenario.
        if ($cart === null) {
            $empty = $command->isGuest()
                ? Cart::createForGuest(GuestSessionId::fromString((string) $command->guestSessionId))
                : Cart::createForUser(UserId::fromString($command->getUserId()));
            return CartDTO::fromEntity($empty);
        }

        $cart->clear();
        $this->cartRepository->save($cart);

        $this->auditLogger->log(new AuditEvent(
            actor: $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'sales.cart_clear',
            resource: 'cart:' . $cart->getId()->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: ['isGuest' => $command->isGuest()],
        ));

        return CartDTO::fromEntity($cart);
    }

    private function resolveCart(ClearCartCommand $command): ?Cart
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
