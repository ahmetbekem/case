<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Queries;

use Application\Catalog\DTOs\ProductDTO;
use Application\Catalog\Queries\ListProductsQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

final class ListProductsHandler extends AbstractQueryHandler
{
    private const int MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return ListProductsQuery::class;
    }

    /**
     * @return array{items: array<int, ProductDTO>, meta: array<string, int>}
     */
    protected function doHandle(QueryInterface $query): array
    {
        assert($query instanceof ListProductsQuery);

        $page = max(1, $query->page);
        $pageSize = max(1, min(self::MAX_PAGE_SIZE, $query->pageSize));

        $products = $this->productRepository->paginate(
            page: $page,
            pageSize: $pageSize,
            search: $query->search,
            activeOnly: $query->activeOnly,
        );
        $total = $this->productRepository->countAll(
            search: $query->search,
            activeOnly: $query->activeOnly,
        );

        return [
            'items' => ProductDTO::collection($products),
            'meta' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $total === 0 ? 0 : (int) ceil($total / $pageSize),
            ],
        ];
    }
}
