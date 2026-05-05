<?php

declare(strict_types=1);

namespace Application\Sales\Handlers\Queries;

use Application\Sales\DTOs\OrderDTO;
use Application\Sales\Queries\ListUserOrdersQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Sales\Contracts\OrderRepositoryInterface;
use Domain\Shared\ValueObjects\UserId;
use Psr\Log\LoggerInterface;

final class ListUserOrdersHandler extends AbstractQueryHandler
{
    private const int MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return ListUserOrdersQuery::class;
    }

    /**
     * @return array{items: array<int, OrderDTO>, meta: array<string, int>}
     */
    protected function doHandle(QueryInterface $query): array
    {
        assert($query instanceof ListUserOrdersQuery);

        $page = max(1, $query->page);
        $pageSize = max(1, min(self::MAX_PAGE_SIZE, $query->pageSize));

        // Admins with adminScope=true see every order; everyone else
        // (including admins without the flag) gets only their own.
        $isAdminScope = $query->adminScope && $query->userRole === 'admin';

        if ($isAdminScope) {
            $orders = $this->orderRepository->paginateAll($page, $pageSize);
            $total = $this->orderRepository->countAll();
        } else {
            $userId = UserId::fromString($query->getUserId());
            $orders = $this->orderRepository->findByUser($userId, $page, $pageSize);
            $total = $this->orderRepository->countForUser($userId);
        }

        return [
            'items' => OrderDTO::collection($orders),
            'meta' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $total === 0 ? 0 : (int) ceil($total / $pageSize),
            ],
        ];
    }
}
