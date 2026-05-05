<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Queries;

use Application\Sales\DTOs\OrderDTO;
use Application\Sales\Queries\GetOrderQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Sales\Contracts\OrderRepositoryInterface;
use Domain\Sales\Exceptions\OrderNotFoundException;
use Domain\Sales\ValueObjects\OrderId;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class GetOrderHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetOrderQuery::class;
    }

    /**
     * @throws OrderNotFoundException
     */
    protected function doHandle(QueryInterface $query): OrderDTO
    {
        assert($query instanceof GetOrderQuery);

        $order = $this->orderRepository->findById(OrderId::fromString($query->orderId));

        // Both "doesn't exist" and "exists but not yours" surface as 404
        // (assertCanBeViewedBy throws OrderAccessDeniedException, also 404).
        if ($order === null) {
            throw OrderNotFoundException::withId($query->orderId);
        }

        $order->assertCanBeViewedBy(
            UserId::fromString($query->getUserId()),
            $query->userRole,
        );

        return OrderDTO::fromEntity($order);
    }
}
