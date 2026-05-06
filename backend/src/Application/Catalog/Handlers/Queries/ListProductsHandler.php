<?php

declare(strict_types=1);

namespace Application\Catalog\Handlers\Queries;

use Application\Catalog\DTOs\ProductDTO;
use Application\Catalog\Queries\ListProductsQuery;
use Application\Shared\Abstracts\AbstractQueryHandler;
use Application\Shared\Contracts\QueryInterface;
use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use Domain\Shared\ValueObjects\Currency;
use Domain\Shared\ValueObjects\Money;
use Domain\System\Services\SettingResolver;
use Psr\Log\LoggerInterface;

final class ListProductsHandler extends AbstractQueryHandler
{
    private const int MAX_PAGE_SIZE = 100;

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

        $items = $this->buildItems($products, $query->displayCurrency);

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $total === 0 ? 0 : (int) ceil($total / $pageSize),
            ],
        ];
    }

    /**
     * Her ürün, kendi currency'sinden display'e dönüştürülür. System
     * base_currency yalnızca yeni ürün create default'u — mevcut
     * ürünler kendi currency snapshot'larını taşır (ADIM 6 alignment
     * migration'ına kadar mixed olabilir).
     *
     * Aynı currency veya kur yoksa: display alanları null (UI legacy
     * price+currency'e veya inverse rate'e düşer).
     *
     * Per-currency rate cache — N farklı currency için en fazla N
     * findLatest çağrısı.
     *
     * @param array<int, \Domain\Catalog\Entities\Product> $products
     * @return array<int, ProductDTO>
     */
    private function buildItems(array $products, ?string $displayCurrencyParam): array
    {
        $display = $displayCurrencyParam !== null
            ? (Currency::tryFrom($displayCurrencyParam) ?? $this->settingResolver->getDefaultDisplayCurrency())
            : $this->settingResolver->getDefaultDisplayCurrency();

        $rateCache = [];
        $dtos = [];
        foreach ($products as $product) {
            $productCurrency = $product->getPrice()->currency;
            if ($productCurrency === $display) {
                $dtos[] = ProductDTO::fromEntity($product);
                continue;
            }
            $cacheKey = $productCurrency->value;
            if (!array_key_exists($cacheKey, $rateCache)) {
                $rate = $this->exchangeRateRepository->findLatest($productCurrency, $display);
                $rateCache[$cacheKey] = $rate?->getRate();
            }
            $rateValue = $rateCache[$cacheKey];
            if ($rateValue === null) {
                $dtos[] = ProductDTO::fromEntity($product);
                continue;
            }
            $displayMoney = new Money(
                round($product->getPrice()->amount * $rateValue, 2),
                $display,
            );
            $dtos[] = ProductDTO::fromEntityWithDisplay($product, $displayMoney, $rateValue);
        }
        return $dtos;
    }
}
