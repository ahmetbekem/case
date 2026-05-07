<?php

declare(strict_types=1);

namespace Domain\Catalog\Contracts;

use Domain\Catalog\Entities\Product;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\Contracts\RepositoryInterface;

/**
 * @extends RepositoryInterface<Product, ProductId>
 */
interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findById(ProductId $id): ?Product;

    public function findBySku(string $sku): ?Product;

    public function existsBySku(string $sku): bool;

    /**
     * @return array<int, Product>
     */
    public function paginate(
        int $page,
        int $pageSize,
        ?string $search = null,
        bool $activeOnly = true,
    ): array;

    public function countAll(?string $search = null, bool $activeOnly = true): int;

    public function save(Product $product): void;

    public function softDelete(ProductId $id): void;

    /**
     * ADR-016 — Tek SQL UPDATE ile, currency=$from olan tüm ürünleri
     * yeni base'e çevirir: price *= $rate, currency = $to. Etkilenen
     * satır sayısını döner. Admin base_currency değişikliği için.
     *
     * Domain entity yüklenmez (binlerce ürün için verimsiz olurdu);
     * bulk SQL kullanır. Caller (handler) bunu transaction içinde
     * çağırmalı ve CHECK constraint hatasını açıklayıcı domain
     * exception'a çevirmeli.
     */
    public function repriceAll(\Domain\Shared\ValueObjects\Currency $from, \Domain\Shared\ValueObjects\Currency $to, float $rate): int;
}
