<?php

declare(strict_types=1);

namespace Application\Catalog\DTOs;

use Application\Shared\Abstracts\AbstractOutputDTO;
use Domain\Catalog\Entities\Product;
use Domain\Shared\ValueObjects\Money;
use OpenApi\Attributes as OA;

/**
 * Product output payload.
 *
 * Tek base currency mimarisinde (ADR-016):
 *   - basePrice + baseCurrency: ürünün DB'deki "kanonik" tutarı
 *   - displayPrice + displayCurrency + exchangeRate: query parametresine
 *     göre runtime conversion sonucu (yalnızca farklı currency talep
 *     edilmişse dolu, aksi halde null)
 *
 * Geriye uyumluluk için `price` ve `currency` alanları KORUNDU —
 * basePrice ile aynı değeri taşırlar; mevcut frontend client'ları
 * kırılmasın diye. ADIM 8'de frontend basePrice/baseCurrency'e geçince
 * bu alanlar deprecate olabilir.
 */
#[OA\Schema(
    schema: 'ProductDTO',
    title: 'Product',
    description: 'Ürün kaydı — basePrice/baseCurrency kanonik DB tutarı; '
        . 'displayPrice/displayCurrency/exchangeRate yalnızca query\'de '
        . 'displayCurrency=X verilmişse dolu olur. price+currency legacy '
        . 'alias (basePrice ile aynı değer).',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'sku', type: 'string', example: 'BABT-SYAH-42'),
        new OA\Property(property: 'name', type: 'string', example: 'Babet Ayakkabı Siyah 42'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'basePrice', type: 'number', format: 'float', example: 1000.00, description: 'DB\'deki kanonik tutar'),
        new OA\Property(property: 'baseCurrency', type: 'string', enum: ['TRY', 'USD', 'EUR'], example: 'TRY'),
        new OA\Property(property: 'displayPrice', type: 'number', format: 'float', nullable: true, description: 'displayCurrency=X verildiyse çevrilmiş tutar'),
        new OA\Property(property: 'displayCurrency', type: 'string', enum: ['TRY', 'USD', 'EUR'], nullable: true),
        new OA\Property(property: 'exchangeRate', type: 'number', format: 'float', nullable: true, description: 'base→display kuru (1.0 ise base==display veya kur yoksa)'),
        new OA\Property(property: 'price', type: 'number', format: 'float', description: '[Legacy] basePrice ile aynı'),
        new OA\Property(property: 'currency', type: 'string', enum: ['TRY', 'USD', 'EUR'], description: '[Legacy] baseCurrency ile aynı'),
        new OA\Property(property: 'stock', type: 'integer', example: 50),
        new OA\Property(property: 'isActive', type: 'boolean', example: true),
        new OA\Property(property: 'createdBy', type: 'string', format: 'uuid'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final readonly class ProductDTO extends AbstractOutputDTO
{
    public function __construct(
        public string $id,
        public string $sku,
        public string $name,
        public ?string $description,
        public float $basePrice,
        public string $baseCurrency,
        public ?float $displayPrice,
        public ?string $displayCurrency,
        public ?float $exchangeRate,
        // Legacy alias (price + currency) — frontend ADIM 8'de basePrice'a geçecek
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
        $base = $product->getPrice();
        return new self(
            id: $product->getId()->value(),
            sku: $product->getSku(),
            name: $product->getName(),
            description: $product->getDescription(),
            basePrice: $base->amount,
            baseCurrency: $base->currency->value,
            displayPrice: null,
            displayCurrency: null,
            exchangeRate: null,
            price: $base->amount,
            currency: $base->currency->value,
            stock: $product->getStock(),
            isActive: $product->isActive(),
            createdBy: $product->getCreatedBy()->value(),
            createdAt: $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $product->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    public static function fromEntityWithDisplay(
        Product $product,
        Money $displayPrice,
        float $exchangeRate,
    ): self {
        $base = $product->getPrice();
        return new self(
            id: $product->getId()->value(),
            sku: $product->getSku(),
            name: $product->getName(),
            description: $product->getDescription(),
            basePrice: $base->amount,
            baseCurrency: $base->currency->value,
            displayPrice: $displayPrice->amount,
            displayCurrency: $displayPrice->currency->value,
            exchangeRate: $exchangeRate,
            price: $base->amount,
            currency: $base->currency->value,
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
