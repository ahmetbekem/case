<?php

declare(strict_types=1);

namespace Application\Catalog\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Catalog\Entities\Product;

final readonly class ProductDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $id,
        public string $sku,
        public string $name,
        public ?string $description,
        public float $price,
        public string $currency,
        public int $stock,
        public bool $isActive,
        public string $createdBy,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->getId()->value(),
            sku: $product->getSku(),
            name: $product->getName(),
            description: $product->getDescription(),
            price: $product->getPrice()->amount,
            currency: $product->getPrice()->currency->value,
            stock: $product->getStock(),
            isActive: $product->isActive(),
            createdBy: $product->getCreatedBy()->value(),
            createdAt: $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $product->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param array<int, Product> $products
     * @return array<int, self>
     */
    public static function collection(array $products): array
    {
        return array_map(fn (Product $p) => self::fromEntity($p), $products);
    }
}
