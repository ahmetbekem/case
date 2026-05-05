<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Commands;

use Application\Sales\Commands\CancelOrderCommand;
use Application\Sales\DTOs\OrderDTO;
use Application\Shared\Abstracts\AbstractCommandHandler;
use Application\Shared\Contracts\CommandInterface;
use Application\Shared\Contracts\TransactionInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Logging\Contracts\AuditLoggerInterface;
use Domain\Logging\ValueObjects\AuditEvent;
use Domain\Sales\Contracts\OrderRepositoryInterface;
use Domain\Sales\Exceptions\OrderNotFoundException;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

/**
 * Cancellation flow:
 *  - Resolve order → 404 ORDER_NOT_FOUND if missing
 *  - assertCanBeCancelledBy → 404 ORDER_NOT_FOUND for non-owner non-admin
 *    (deliberate IDOR-safe response, see OrderAccessDeniedException)
 *  - order.cancel(reason) — 409 ORDER_CANNOT_BE_CANCELLED if not pending
 *  - Restore stock for each line (best-effort restock; if a product was
 *    soft-deleted between sale and cancel we silently skip it — the
 *    audit metadata records what was actually restored).
 *  - Emit sales.order_cancel audit
 */
final class CancelOrderHandler extends AbstractCommandHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AuditLoggerInterface $auditLogger,
        TransactionInterface $transaction,
        LoggerInterface $logger,
    ) {
        parent::__construct($transaction, $logger);
    }

    public static function getHandledCommandClass(): string
    {
        return CancelOrderCommand::class;
    }

    protected function doHandle(CommandInterface $command): OrderDTO
    {
        assert($command instanceof CancelOrderCommand);

        $orderId = OrderId::fromString($command->orderId);
        $order = $this->orderRepository->findById($orderId);

        if ($order === null) {
            throw OrderNotFoundException::withId($command->orderId);
        }

        $order->assertCanBeCancelledBy(
            UserId::fromString($command->getUserId()),
            $command->userRole,
        );

        $order->cancel($command->reason);

        // Restock — best effort
        $restocked = [];
        foreach ($order->getItems() as $item) {
            $product = $this->productRepository->findById(ProductId::fromString($item->getProductId()));
            if ($product === null) {
                continue; // soft-deleted between sale and cancel
            }
            $product->increaseStock($item->getQuantity());
            $this->productRepository->save($product);
            $restocked[] = ['productId' => $item->getProductId(), 'quantity' => $item->getQuantity()];
        }

        $this->orderRepository->save($order);

        $this->auditLogger->log(new AuditEvent(
            actor: 'userId:' . $command->getUserId(),
            actorIp: $command->getUserIp(),
            action: 'sales.order_cancel',
            resource: 'order:' . $command->orderId,
            module: 'sales',
            result: 'success',
            correlationId: $command->getCorrelationId(),
            metadata: [
                'reason' => $command->reason,
                'restocked' => $restocked,
            ],
        ));

        return OrderDTO::fromEntity($order);
    }
}
