<?php

declare(strict_types=1);

namespace Infrastructure\Catalog\Repositories;

use Domain\Catalog\Contracts\ProductRepositoryInterface;
use Domain\Catalog\Entities\Product;
use Domain\Catalog\ValueObjects\ProductId;
use Domain\Shared\Contracts\ValueObjectInterface;
use Domain\Shared\ValueObjects\Currency;
use Illuminate\Support\Facades\DB;
use Infrastructure\Catalog\Mappers\ProductMapper;
use Infrastructure\Catalog\Models\ProductModel;
use Infrastructure\Shared\Abstracts\AbstractEloquentRepository;

/**
 * @extends AbstractEloquentRepository<Product, ProductId, ProductModel>
 */
final class EloquentProductRepository extends AbstractEloquentRepository implements ProductRepositoryInterface
{
    public function __construct(ProductMapper $mapper)
    {
        parent::__construct($mapper);
    }

    protected function getModelClass(): string
    {
        return ProductModel::class;
    }

    protected function createId(string $id): ValueObjectInterface
    {
        return ProductId::fromString($id);
    }

    public function findById(ProductId $id): ?Product
    {
        $model = $this->query()->where('id', $id->value())->first();
        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function findBySku(string $sku): ?Product
    {
        $model = $this->query()->where('sku', $sku)->first();
        return $model ? $this->mapper->toEntity($model) : null;
    }

    public function existsBySku(string $sku): bool
    {
        return $this->query()->where('sku', $sku)->exists();
    }

    public function paginate(
        int $page,
        int $pageSize,
        ?string $search = null,
        bool $activeOnly = true,
    ): array {
        $query = $this->buildListingQuery($search, $activeOnly);

        $models = $query
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        return $models->map(fn ($model) => $this->mapper->toEntity($model))->all();
    }

    public function countAll(?string $search = null, bool $activeOnly = true): int
    {
        return $this->buildListingQuery($search, $activeOnly)->count();
    }

    public function save(Product $product): void
    {
        ProductModel::query()->updateOrInsert(
            ['id' => $product->getId()->value()],
            $this->mapper->toPersistence($product),
        );
    }

    public function softDelete(ProductId $id): void
    {
        $this->query()->where('id', $id->value())->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function repriceAll(Currency $from, Currency $to, float $rate): int
    {
        if ($rate <= 0) {
            throw new \InvalidArgumentException('Reprice rate must be positive');
        }
        // ROUND ile 2 decimal — chk_products_price (>= 0) ve hassasiyet
        // korunur. CHECK constraint kur sıfıra yakınsa patlayabilir;
        // caller stale rate'i çağırmadan önce eler.
        return DB::table('catalog.products')
            ->where('currency', $from->value)
            ->update([
                'price' => DB::raw('ROUND(price * ' . $rate . '::numeric, 2)'),
                'currency' => $to->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * Shared filter logic for list + count so the two never drift.
     * ILIKE matches the GIN trigram index from migration
     * `idx_products_name_trgm` (catalog/000200), giving cheap fuzzy
     * search even on large catalogues.
     */
    private function buildListingQuery(?string $search, bool $activeOnly): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($search !== null && trim($search) !== '') {
            $query->where('name', 'ILIKE', '%' . trim($search) . '%');
        }

        return $query;
    }
}
