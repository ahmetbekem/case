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
use Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\System\Services\SettingResolver;
use Psr\Log\LoggerInterface;

final class GetProductHandler extends AbstractQueryHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
        private readonly SettingResolver $settingResolver,
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

        $display = $query->displayCurrency !== null
            ? (Currency::tryFrom($query->displayCurrency) ?? $this->settingResolver->getDefaultDisplayCurrency())
            : $this->settingResolver->getDefaultDisplayCurrency();

        // Ürünün kendi currency'sinden display'e çevir — list handler ile
        // aynı mantık. System base_currency yalnızca yeni create default'u.
        $productCurrency = $product->getPrice()->currency;
        if ($productCurrency === $display) {
            return ProductDTO::fromEntity($product);
        }

        $rate = $this->exchangeRateRepository->findLatest($productCurrency, $display);
        if ($rate === null) {
            return ProductDTO::fromEntity($product);
        }

        $displayMoney = new Money(
            round($product->getPrice()->amount * $rate->getRate(), 2),
            $display,
        );
        return ProductDTO::fromEntityWithDisplay($product, $displayMoney, $rate->getRate());
    }
}
