<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Queries;

use Application\Catalog\DTOs\ProductDTO;
use Application\Catalog\Queries\GetProductQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Exceptions\ProductNotFoundException;
use Domain\Catalog\ValueObjects\ProductId;
use Psr\Log\LoggerInterface;

final class GetProductHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    public static function getHandledQueryClass(): string
    {
        return GetProductQuery::class;
    }

    /**
     * @throws ProductNotFoundException
     */
    protected function doHandle(QueryInterface $query): ProductDTO
    {
        assert($query instanceof GetProductQuery);

        $product = $this->productRepository->findById(ProductId::fromString($query->productId));

        if ($product === null) {
            throw ProductNotFoundException::withId($query->productId);
        }

        return ProductDTO::fromEntity($product);
    }
}
