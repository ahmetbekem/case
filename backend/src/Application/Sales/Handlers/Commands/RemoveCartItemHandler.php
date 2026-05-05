<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Commands;

use Application\Sales\Commands\RemoveCartItemCommand;
use Application\Sales\DTOs\CartDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Sales\Contracts\CartRepositoryInterface;
use Domain\Sales\Exceptions\CartNotFoundException;
use Domain\Sales\ValueObjects\CartItemId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class RemoveCartItemHandler extends AbstractCommandHandler
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
        return RemoveCartItemCommand::class;
    }

    protected function doHandle(CommandInterface $command): CartDTO
    {
        assert($command instanceof RemoveCartItemCommand);

        $userId = UserId::fromString($command->getUserId());
        $cart = $this->cartRepository->findActiveCartForUser($userId);

        if ($cart === null) {
            throw CartNotFoundException::forUser($command->getUserId());
        }

        $cart->removeItem(CartItemId::fromString($command->cartItemId));
        $this->cartRepository->save($cart);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'sales.cart_remove_item',
            resource: 'cart:' . $cart->getId()->value(),
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: ['cartItemId' => $command->cartItemId],
        ));

        return CartDTO::fromEntity($cart);
    }
}
