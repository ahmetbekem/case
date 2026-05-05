<?php

declare(strict_types=1);

namespace Infrastructure\Catalog\Mappers;

use Domain\Catalog\Entities\Product;
use Domain\Shared\Abstracts\AbstractMapper;
use Infrastructure\Catalog\Models\ProductModel;

final class ProductMapper extends AbstractMapper
{
    public function toEntity(object $model): Product
    {
        assert($model instanceof ProductModel);

        return Product::fromArray([
            'id' => $model->id,
            'sku' => $model->sku,
            'name' => $model->name,
            'description' => $model->description,
            'price' => (float) $model->price,
            'currency' => $model->currency,
            'stock' => (int) $model->stock,
            'isActive' => (bool) $model->is_active,
            'createdBy' => $model->created_by,
            'createdAt' => $model->created_at->format(\DateTimeInterface::ATOM),
            'updatedAt' => $model->updated_at?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(object $entity): array
    {
        assert($entity instanceof Product);

        return [
            'id' => $entity->getId()->value(),
            'sku' => $entity->getSku(),
            'name' => $entity->getName(),
            'description' => $entity->getDescription(),
            'price' => $entity->getPrice()->amount,
            'currency' => $entity->getPrice()->currency->value,
            'stock' => $entity->getStock(),
            'is_active' => $entity->isActive(),
            'created_by' => $entity->getCreatedBy()->value(),
            'created_at' => $entity->getCreatedAt(),
            'updated_at' => $entity->getUpdatedAt(),
        ];
    }
}
